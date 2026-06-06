<?php
/**
 * Plugin Name: Xenarch
 * Plugin URI:  https://xenarch.com/wordpress
 * Description: Monetize AI bot traffic on your WordPress site. Xenarch detects AI agents and charges micropayments for content access via the x402 protocol.
 * Version:     1.10.1
 * Author:      Xenarch
 * Author URI:  https://xenarch.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xenarch
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'XENARCH_VERSION', '1.10.1' );
define( 'XENARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XENARCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'XENARCH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * XEN-384: short-circuit page caches for x402 payment-proof replays.
 *
 * When an agent re-fetches a gated URL carrying the canonical Xenarch
 * replay headers (``X-Xenarch-Tx-Hash`` + ``X-Xenarch-Gate-Id``), our
 * middleware MUST run so it can verify the on-chain tx and call the
 * platform's /v1/gates/<id>/verify endpoint. If a WordPress page-cache
 * plugin (W3 Total Cache, WP Super Cache, LiteSpeed, Comet, etc.)
 * intercepts the request first and serves a cached HTML response, the
 * middleware never runs → no verify → publisher's earnings feed misses
 * the payment.
 *
 * Defined as early as possible (before any class file is required) so
 * the DONOTCACHEPAGE / DONOTCACHCEOBJECT constants take effect before
 * cache plugins make their serve-or-pass decision. Most full-page
 * cache plugins look for these constants at the ``plugins_loaded``
 * boundary; advanced-cache.php-based intercepts that run earlier
 * still get the ``Cache-Control: no-store`` header echoed back.
 *
 * Cheap: a couple of $_SERVER reads + a couple of define()s. Runs on
 * every WP request, but only fires when these headers are present
 * (which is rare — only the agent replay path).
 */
if ( ! function_exists( 'xenarch_bypass_cache_for_payment_replay' ) ) {
	function xenarch_bypass_cache_for_payment_replay() {
		if ( empty( $_SERVER['HTTP_X_XENARCH_TX_HASH'] )
			&& empty( $_SERVER['HTTP_X_XENARCH_GATE_ID'] ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}
		if ( ! defined( 'DONOTCACHCEOBJECT' ) ) {
			define( 'DONOTCACHCEOBJECT', true );
		}
		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
			header( 'Pragma: no-cache', true );
			header( 'Vary: X-Xenarch-Tx-Hash, X-Xenarch-Gate-Id', false );
		}
	}
	xenarch_bypass_cache_for_payment_replay();
}

require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-api.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-payment-proof.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-bot-detect.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-browser-proof.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-admin.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-frontend.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-gate.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-gate-response.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-discovery.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-rest.php';

/**
 * Activation hook. Post-XEN-380 the plugin is a thin window into the
 * platform: payout wallet, pricing rules, gating defaults, bot category
 * toggles, per-bot overrides, and detection telemetry all live in the
 * Xenarch backend. The only persistent state this plugin owns is the
 * ``site_token`` (its credential pointing back at the platform) and an
 * HMAC secret for the browser-challenge cookie. Both have to be local
 * by definition.
 */
function xenarch_activate() {
	// Plugin-local credential. Empty until the merchant runs the claim
	// handshake from the admin → dash.xenarch.dev/sites/claim.
	if ( false === get_option( 'xenarch_site_token' ) ) {
		add_option( 'xenarch_site_token', '' );
	}
	if ( false === get_option( 'xenarch_site_id' ) ) {
		add_option( 'xenarch_site_id', '' );
	}

	// HMAC secret for the JS-challenge cookie. Stays local — never
	// crosses the wire.
	if ( false === get_option( Xenarch_Browser_Proof::SECRET_OPTION )
	     && function_exists( 'wp_generate_password' ) ) {
		add_option( Xenarch_Browser_Proof::SECRET_OPTION, wp_generate_password( 64, true, true ) );
	}

	// XEN-394 v2: detection events POST directly to the platform per
	// request (Xenarch_Gate::log_bot_detection → Xenarch_Api::post_bot_detections,
	// blocking=false). No local table to create. The legacy
	// wp_xenarch_bot_log table from <=1.8.0 is dropped by
	// xenarch_run_upgrade_migrations on the next plugins_loaded.

	$discovery = new Xenarch_Discovery();
	$discovery->add_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'xenarch_activate' );

function xenarch_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'xenarch_deactivate' );

function xenarch_init() {
	if ( is_admin() ) {
		new Xenarch_Admin();
	}

	// Frontend l.js loading — disabled until cdn.xenarch.dev is live.
	// new Xenarch_Frontend();

	new Xenarch_Gate();
	new Xenarch_Discovery();
	new Xenarch_Rest();
}
add_action( 'plugins_loaded', 'xenarch_init' );

/**
 * XEN-394 v2: on plugin upgrade, drop the local wp_xenarch_bot_log table
 * and clean up the legacy sync state. Detection events now POST directly
 * to the platform per-request (Xenarch_Gate::log_bot_detection) — no
 * local table, no cron, no sync option. Platform is canonical.
 *
 * Idempotent: re-run safely. Uses XENARCH_VERSION + a stored option so
 * the migration only runs once per upgrade. Per dev-phase rules (no real
 * users yet) we hard-drop without a deprecation cycle.
 */
function xenarch_run_upgrade_migrations() {
	$installed = get_option( 'xenarch_installed_version', '0.0.0' );
	if ( version_compare( $installed, XENARCH_VERSION, '>=' ) ) {
		return;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'xenarch_bot_log';
	$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore
	delete_option( 'xenarch_bot_log_last_sync_at' );
	// Cancel any leftover daily cron from 1.8.0 — the action no longer
	// has a handler, but unscheduling keeps wp-cron clean.
	$timestamp = wp_next_scheduled( 'xenarch_flush_bot_log_cron' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'xenarch_flush_bot_log_cron' );
	}
	update_option( 'xenarch_installed_version', XENARCH_VERSION );
}
add_action( 'plugins_loaded', 'xenarch_run_upgrade_migrations', 5 );

// XEN-394 v2: xenarch_create_bot_log_table() removed — detections now
// POST directly to the platform (no local table). The legacy table is
// dropped by xenarch_run_upgrade_migrations on plugin upgrade.

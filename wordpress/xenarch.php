<?php
/**
 * Plugin Name: Xenarch
 * Plugin URI:  https://xenarch.com/wordpress
 * Description: Monetize AI bot traffic on your WordPress site. Xenarch detects AI agents and charges micropayments for content access via the x402 protocol.
 * Version:     1.8.0
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

define( 'XENARCH_VERSION', '1.8.0' );
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
 * toggles all live in the Xenarch backend. The only persistent state
 * this plugin owns is the ``site_token`` (its credential pointing back
 * at the platform) and a local bot-detection log table that records
 * traffic hitting *this* WP server in particular.
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

	// Per-server bot detection log. Lives locally because the events
	// happen on *this* server — the platform doesn't see free-page hits
	// directly, only paid ones. XEN-394: a daily wp_cron flushes the
	// table's current state to the platform's bot_detections endpoint
	// so the dashboard /bots Cross-site activity panel can aggregate
	// across all the publisher's sites.
	xenarch_create_bot_log_table();

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
 * XEN-394: daily cron — flush new/updated wp_xenarch_bot_log rows to
 * the platform so the dashboard /bots Cross-site activity panel can
 * aggregate. The plugin sends current running totals; the platform's
 * upsert reconciles via LEAST/GREATEST so retries are idempotent.
 *
 * "Updated since last sync" = rows with last_seen > xenarch_bot_log_last_sync_at.
 * On success, last_sync_at is bumped to the most-recent last_seen we sent
 * (NOT to now()) — that way out-of-band log writes after the SELECT
 * still get picked up on the next run.
 */
function xenarch_schedule_bot_log_flush() {
	if ( ! wp_next_scheduled( 'xenarch_flush_bot_log_cron' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'xenarch_flush_bot_log_cron' );
	}
}
add_action( 'init', 'xenarch_schedule_bot_log_flush' );

function xenarch_unschedule_bot_log_flush() {
	$timestamp = wp_next_scheduled( 'xenarch_flush_bot_log_cron' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'xenarch_flush_bot_log_cron' );
	}
}
register_deactivation_hook( __FILE__, 'xenarch_unschedule_bot_log_flush' );

function xenarch_flush_bot_log_to_platform() {
	$site_token = get_option( 'xenarch_site_token', '' );
	if ( empty( $site_token ) ) {
		return; // Not claimed yet — nothing to send.
	}

	global $wpdb;
	$table        = $wpdb->prefix . 'xenarch_bot_log';
	$last_sync_at = get_option( 'xenarch_bot_log_last_sync_at', '1970-01-01 00:00:00' );

	// Pull rows updated since the last sync. Cap at 500 per run to match
	// the platform's batch limit; if more accumulate, the next cron run
	// picks up the rest (last_sync_at advances incrementally).
	$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix constant.
		"SELECT signature, category, company, first_seen, last_seen, hit_count
		 FROM $table
		 WHERE last_seen > %s
		 ORDER BY last_seen ASC
		 LIMIT 500",
		$last_sync_at
	), ARRAY_A );

	if ( empty( $rows ) ) {
		return;
	}

	$detections = array();
	$max_last_seen = $last_sync_at;
	foreach ( $rows as $row ) {
		$ls = $row['last_seen'];
		$fs = $row['first_seen'];
		$detections[] = array(
			'signature'  => (string) $row['signature'],
			'category'   => (string) ( $row['category'] ?? '' ),
			'company'    => (string) ( $row['company'] ?? '' ),
			// ISO-8601 UTC. WP's current_time('mysql', true) writes
			// "Y-m-d H:i:s" without a tz offset — append Z so the
			// platform's Pydantic datetime parses it as UTC.
			'first_seen' => str_replace( ' ', 'T', $fs ) . 'Z',
			'last_seen'  => str_replace( ' ', 'T', $ls ) . 'Z',
			'hit_count'  => (int) $row['hit_count'],
		);
		if ( strcmp( $ls, $max_last_seen ) > 0 ) {
			$max_last_seen = $ls;
		}
	}

	$api      = new Xenarch_Api();
	$response = $api->post_bot_detections( $detections );
	if ( is_wp_error( $response ) ) {
		// Don't advance last_sync_at — retry on the next run.
		error_log( '[xenarch] bot-log flush failed: ' . $response->get_error_message() );
		return;
	}
	update_option( 'xenarch_bot_log_last_sync_at', $max_last_seen );
}
add_action( 'xenarch_flush_bot_log_cron', 'xenarch_flush_bot_log_to_platform' );

function xenarch_create_bot_log_table() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'xenarch_bot_log';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		signature varchar(255) NOT NULL,
		category varchar(50) NOT NULL DEFAULT '',
		company varchar(100) NOT NULL DEFAULT '',
		first_seen datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		last_seen datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY signature (signature),
		KEY category (category),
		KEY last_seen (last_seen)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

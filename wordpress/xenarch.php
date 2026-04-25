<?php
/**
 * Plugin Name: Xenarch
 * Plugin URI:  https://xenarch.com/wordpress
 * Description: Monetize AI bot traffic on your WordPress site. Xenarch detects AI agents and charges micropayments for content access via the x402 protocol.
 * Version:     1.1.1
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

/**
 * Plugin constants.
 */
define( 'XENARCH_VERSION', '1.1.1' );
define( 'XENARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XENARCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'XENARCH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin includes.
 */
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
 * Activation hook — set default options.
 */
function xenarch_activate() {
	// Only set defaults if options do not already exist.
	if ( false === get_option( 'xenarch_api_key' ) ) {
		add_option( 'xenarch_api_key', '' );
	}
	if ( false === get_option( 'xenarch_site_id' ) ) {
		add_option( 'xenarch_site_id', '' );
	}
	if ( false === get_option( 'xenarch_site_token' ) ) {
		add_option( 'xenarch_site_token', '' );
	}
	if ( false === get_option( 'xenarch_default_price' ) ) {
		add_option( 'xenarch_default_price', '0.003' );
	}
	if ( false === get_option( 'xenarch_payout_wallet' ) ) {
		add_option( 'xenarch_payout_wallet', '' );
	}
	if ( false === get_option( 'xenarch_gate_unknown_traffic' ) ) {
		add_option( 'xenarch_gate_unknown_traffic', '1' );
	}
	if ( false === get_option( 'xenarch_pricing_rules' ) ) {
		add_option( 'xenarch_pricing_rules', '[]' );
	}
	// Bot classification options.
	if ( false === get_option( 'xenarch_gate_enabled' ) ) {
		add_option( 'xenarch_gate_enabled', '1' );
	}
	if ( false === get_option( 'xenarch_bot_categories' ) ) {
		add_option( 'xenarch_bot_categories', wp_json_encode( array(
			'ai_search'     => 'allow',
			'ai_assistants' => 'charge',
			'ai_agents'     => 'charge',
			'ai_training'   => 'charge',
			'scrapers'      => 'charge',
			'general_ai'    => 'charge',
		) ) );
	}
	if ( false === get_option( 'xenarch_bot_overrides' ) ) {
		add_option( 'xenarch_bot_overrides', '{}' );
	}
	if ( false === get_option( 'xenarch_wallet_type' ) ) {
		add_option( 'xenarch_wallet_type', '' );
	}
	if ( false === get_option( 'xenarch_wallet_network' ) ) {
		add_option( 'xenarch_wallet_network', 'base' );
	}

	if ( false === get_option( Xenarch_Browser_Proof::SECRET_OPTION ) && function_exists( 'wp_generate_password' ) ) {
		add_option( Xenarch_Browser_Proof::SECRET_OPTION, wp_generate_password( 64, true, true ) );
	}

	// Create bot detection log table.
	xenarch_create_bot_log_table();

	// Register rewrite rules before flushing so they get written.
	$discovery = new Xenarch_Discovery();
	$discovery->add_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'xenarch_activate' );

/**
 * Deactivation hook — intentional no-op.
 *
 * We keep all data on deactivation so re-activating restores state.
 * Data cleanup happens only on uninstall (see uninstall.php).
 */
function xenarch_deactivate() {
	// Remove rewrite rules added by the plugin.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'xenarch_deactivate' );

/**
 * Initialise plugin components.
 */
function xenarch_init() {
	// Admin settings page.
	if ( is_admin() ) {
		new Xenarch_Admin();
	}

	// Frontend l.js loading — disabled until cdn.xenarch.dev is live.
	// new Xenarch_Frontend();

	// Server-side bot gating (returns 402 for detected AI crawlers).
	new Xenarch_Gate();

	// Discovery documents (/.well-known/pay.json and /.well-known/xenarch.md).
	new Xenarch_Discovery();

	// REST API endpoints (must be available outside admin for AJAX).
	new Xenarch_Rest();
}
add_action( 'plugins_loaded', 'xenarch_init' );

/**
 * Create the bot detection log table.
 */
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

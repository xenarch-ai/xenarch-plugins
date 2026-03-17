<?php
/**
 * Plugin Name: Xenarch
 * Plugin URI:  https://xenarch.com
 * Description: Monetize AI bot traffic on your WordPress site. Xenarch detects AI agents and charges micropayments for content access via the x402 protocol.
 * Version:     1.0.0
 * Author:      Xenarch
 * Author URI:  https://xenarch.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xenarch
 * Requires PHP: 7.4
 * Requires at least: 5.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'XENARCH_VERSION', '1.0.0' );
define( 'XENARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XENARCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'XENARCH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin includes.
 */
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-api.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-admin.php';
require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-frontend.php';

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
	if ( false === get_option( 'xenarch_email' ) ) {
		add_option( 'xenarch_email', '' );
	}
	if ( false === get_option( 'xenarch_default_price' ) ) {
		add_option( 'xenarch_default_price', '0.003' );
	}
	if ( false === get_option( 'xenarch_payout_wallet' ) ) {
		add_option( 'xenarch_payout_wallet', '' );
	}
}
register_activation_hook( __FILE__, 'xenarch_activate' );

/**
 * Deactivation hook — intentional no-op.
 *
 * We keep all data on deactivation so re-activating restores state.
 * Data cleanup happens only on uninstall (see uninstall.php).
 */
function xenarch_deactivate() {
	// No-op: keep settings for potential reactivation.
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

	// Frontend l.js loading.
	new Xenarch_Frontend();
}
add_action( 'plugins_loaded', 'xenarch_init' );

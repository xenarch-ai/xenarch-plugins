<?php
/**
 * Xenarch uninstall handler.
 *
 * Removes all plugin data from wp_options when the plugin is deleted
 * via the WordPress admin. This file is called by WordPress core and
 * must not be accessed directly.
 *
 * @package Xenarch
 */

// Prevent direct file access and ensure this is a legitimate uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all Xenarch options.
delete_option( 'xenarch_api_key' );
delete_option( 'xenarch_site_id' );
delete_option( 'xenarch_site_token' );
delete_option( 'xenarch_default_price' );
delete_option( 'xenarch_payout_wallet' );
delete_option( 'xenarch_gate_unknown_traffic' );
delete_option( 'xenarch_pricing_rules' );
delete_option( 'xenarch_browser_proof_secret' );
delete_option( 'xenarch_gate_enabled' );

// Drop bot detection log table.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}xenarch_bot_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
delete_option( 'xenarch_bot_categories' );
delete_option( 'xenarch_bot_overrides' );
delete_option( 'xenarch_wallet_type' );
delete_option( 'xenarch_wallet_network' );

<?php
/**
 * XEN-684 — Features switch: "Bot gating" and "Crypto checkout (WooCommerce)".
 *
 * Each feature is a plugin-local WP option holding '1' or '0':
 *
 *   xenarch_feature_gating    bot gate + discovery docs (pay.json, xenarch.md)
 *   xenarch_feature_checkout  the WooCommerce "Crypto: USDC" payment option
 *
 * An option that was never written means the merchant hasn't chosen yet
 * (fresh install): the admin shows the first-run chooser. Until then the
 * runtime behaves like v1.12.0 (both on). That is harmless on a fresh
 * install: the gate and discovery need a site token, and the WooCommerce
 * option is registered but stays off until a pay link is connected.
 *
 * Existing installs are adopted on upgrade with both features on, so an
 * update never changes what a live site does.
 *
 * Turning a feature off only stops it from loading. Gating settings live on
 * the platform and the WooCommerce connection stays in its own options, so
 * turning a feature back on restores exactly what was there.
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenarch_Features {

	const OPT_GATING   = 'xenarch_feature_gating';
	const OPT_CHECKOUT = 'xenarch_feature_checkout';

	// ------------------------------------------------------------------
	// Pure helpers (no WordPress calls — unit-testable)
	// ------------------------------------------------------------------

	/**
	 * Stored option value → bool. Unset (false) falls back to $default.
	 *
	 * @param mixed $raw     Raw get_option() value.
	 * @param bool  $default Value when the option was never written.
	 * @return bool
	 */
	public static function to_bool( $raw, $default ) {
		if ( false === $raw ) {
			return (bool) $default;
		}
		return '1' === (string) $raw;
	}

	/**
	 * What the first-run chooser preselects. With WooCommerce active and
	 * gating never set up, the merchant most likely came for checkout.
	 *
	 * @param bool $woo_active WooCommerce is active.
	 * @param bool $has_site   A bot-gating site token is stored.
	 * @return array{gating: bool, checkout: bool}
	 */
	public static function suggest( $woo_active, $has_site ) {
		if ( $woo_active && ! $has_site ) {
			return array( 'gating' => false, 'checkout' => true );
		}
		return array( 'gating' => true, 'checkout' => (bool) $woo_active );
	}

	/**
	 * Admin header tagline for the features that are on.
	 *
	 * @param bool $gating   Bot gating on.
	 * @param bool $checkout Crypto checkout on (and usable).
	 * @return string
	 */
	public static function tagline_for( $gating, $checkout ) {
		if ( $gating && $checkout ) {
			return 'Charge the bots that take, and take USDC and USDT at checkout.';
		}
		if ( $checkout ) {
			return 'Take USDC and USDT payments at your WooCommerce checkout.';
		}
		if ( $gating ) {
			return 'Charge the bots that take. Let through the ones that help.';
		}
		return 'Bot gating and crypto checkout for WordPress.';
	}

	/**
	 * Validate a features update. Returns '' when it can be saved, or the
	 * reason it can't.
	 *
	 * @param mixed $checkout   Requested checkout value (null = unchanged).
	 * @param bool  $woo_active WooCommerce is active.
	 * @return string
	 */
	public static function update_problem( $checkout, $woo_active ) {
		if ( true === $checkout && ! $woo_active ) {
			return 'Crypto checkout needs WooCommerce. Install and activate WooCommerce first.';
		}
		return '';
	}

	// ------------------------------------------------------------------
	// State
	// ------------------------------------------------------------------

	public static function woo_active() {
		return class_exists( 'WooCommerce' );
	}

	/** True once the merchant (or the upgrade adoption) has chosen. */
	public static function chosen() {
		return false !== get_option( self::OPT_GATING, false )
			|| false !== get_option( self::OPT_CHECKOUT, false );
	}

	/** Bot gating is switched on (or not chosen yet). */
	public static function gating() {
		return self::to_bool( get_option( self::OPT_GATING, false ), true );
	}

	/** The checkout switch as stored, ignoring whether WooCommerce is active. */
	public static function checkout_switch() {
		return self::to_bool( get_option( self::OPT_CHECKOUT, false ), true );
	}

	/** Crypto checkout is switched on AND WooCommerce is active. */
	public static function checkout() {
		return self::checkout_switch() && self::woo_active();
	}

	public static function tagline() {
		return self::tagline_for( self::gating(), self::checkout() );
	}

	/**
	 * Existing install upgrading to 1.13.0: keep everything on, never ask.
	 * Only writes options that are missing.
	 */
	public static function adopt_existing_install() {
		if ( false === get_option( self::OPT_GATING, false ) ) {
			add_option( self::OPT_GATING, '1' );
		}
		if ( false === get_option( self::OPT_CHECKOUT, false ) ) {
			add_option( self::OPT_CHECKOUT, '1' );
		}
	}

	/** Everything the admin app needs about features. */
	public static function snapshot() {
		$woo      = self::woo_active();
		$has_site = '' !== (string) get_option( 'xenarch_site_token', '' );
		return array(
			'chosen'     => self::chosen(),
			'gating'     => self::gating(),
			'checkout'   => self::checkout_switch() && $woo,
			'woo_active' => $woo,
			'suggested'  => self::suggest( $woo, $has_site ),
			'tagline'    => self::tagline(),
		);
	}

	// ------------------------------------------------------------------
	// REST
	// ------------------------------------------------------------------

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$admin = array( __CLASS__, 'check_admin' );
		register_rest_route(
			Xenarch_Rest::NAMESPACE,
			'/features',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_get' ), 'permission_callback' => $admin ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_save' ), 'permission_callback' => $admin ),
			)
		);
	}

	public static function check_admin() {
		return current_user_can( 'manage_options' );
	}

	public static function rest_get() {
		return new WP_REST_Response( self::snapshot(), 200 );
	}

	/**
	 * Save { gating?: bool, checkout?: bool }. Saving either one marks the
	 * features as chosen, so both are written (the missing one keeps its
	 * current effective value).
	 */
	public static function rest_save( $request ) {
		$body     = $request->get_json_params();
		$body     = is_array( $body ) ? $body : array();
		$gating   = array_key_exists( 'gating', $body ) ? rest_sanitize_boolean( $body['gating'] ) : self::gating();
		// Keep the stored checkout switch (not the effective value) when it
		// isn't being changed, so toggling gating while WooCommerce is
		// briefly inactive doesn't silently switch checkout off for good.
		$checkout = array_key_exists( 'checkout', $body ) ? rest_sanitize_boolean( $body['checkout'] ) : self::checkout_switch();

		$problem = self::update_problem( array_key_exists( 'checkout', $body ) ? $checkout : null, self::woo_active() );
		if ( '' !== $problem ) {
			return new WP_REST_Response( array( 'error' => 'woo_inactive', 'detail' => $problem ), 400 );
		}

		$gating_changed = self::gating() !== $gating;
		update_option( self::OPT_GATING, $gating ? '1' : '0' );
		update_option( self::OPT_CHECKOUT, $checkout ? '1' : '0' );

		if ( $gating_changed ) {
			// The discovery rewrite rules (/.well-known/pay.json, /pay.json,
			// /.well-known/xenarch.md) are only registered while gating is on.
			// Dropping the cached rules makes WordPress rebuild them on the
			// next request from what that request registers.
			delete_option( 'rewrite_rules' );
			// Gating config and pay.json site caches would otherwise outlive
			// an off → on switch by up to their TTL.
			delete_transient( Xenarch_Gate::GATING_CONFIG_TRANSIENT );
			delete_transient( 'xenarch_pay_json_site' );
		}

		return new WP_REST_Response( self::snapshot(), 200 );
	}
}

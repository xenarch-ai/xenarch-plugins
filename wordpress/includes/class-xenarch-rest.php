<?php
/**
 * Xenarch REST API — admin-side glue.
 *
 * Post-XEN-380 the plugin is a thin window into the platform. Almost
 * nothing the merchant cares about lives in WP MySQL — payout wallet,
 * pricing, gating, earnings, transactions all live on the backend and
 * are managed in dash.xenarch.dev.
 *
 * That collapses what this file used to do (proxy ~15 endpoints, write
 * a dozen wp_options) into two responsibilities:
 *
 *   1. ``GET  /xenarch/v1/settings`` — return the *plugin-local* state
 *      the React app needs to decide which screen to render (am I
 *      paired with a site_token yet?). No platform-owned fields here.
 *
 *   2. ``POST /xenarch/v1/claim-exchange`` — finish the claim handshake
 *      kicked off on dash.xenarch.dev/sites/claim. Takes the single-use
 *      claim_token from the dashboard redirect, hands it to the
 *      platform for the real site_token, persists locally.
 *
 * Daily reads/writes against the platform (Settings → Pricing/Gating,
 * Earnings, bot management) are deferred to a follow-up rewrite — for
 * v1.5 those tabs deep-link to the dashboard, where the data already
 * lives.
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenarch_Rest {

	const NAMESPACE = 'xenarch/v1';

	/** @var Xenarch_Api */
	private $api;

	public function __construct() {
		$this->api = new Xenarch_Api();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/claim-exchange',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_claim_exchange' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_disconnect' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// XEN-380 / XEN-383 — thin-window proxies to the platform's
		// /v1/sites/me/* X-Site-Token surface. The React admin treats
		// these as same-origin; PHP forwards each one to api.xenarch.dev
		// with the locally-stored site_token so the browser never has to
		// open a cross-origin request to the platform.
		register_rest_route(
			self::NAMESPACE,
			'/site',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_site' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/site/gating',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'handle_put_gating' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/site/pricing',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'handle_put_pricing' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/site/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_stats' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/site/transactions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_transactions' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/site/category-breakdown',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_category_breakdown' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
	}

	/**
	 * Only WP admins can talk to the plugin REST surface. Standard WP
	 * admin auth — the SIWE wallet identity is a separate concern that
	 * lives on the platform; the WP-side gate here is just "are you the
	 * person who installed this plugin?".
	 */
	public function check_admin() {
		return current_user_can( 'manage_options' );
	}

	public function get_settings() {
		return new WP_REST_Response( $this->snapshot(), 200 );
	}

	/**
	 * Swap a one-time claim_token (issued by dash.xenarch.dev/sites/claim)
	 * for the long-lived site_token. The claim_token only lives in the
	 * URL fragment for one round trip; consuming it here persists the
	 * site_token to wp_options and unlocks the main admin UI.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_claim_exchange( $request ) {
		$params       = $request->get_json_params();
		$claim_token  = isset( $params['claim_token'] ) ? trim( (string) $params['claim_token'] ) : '';

		if ( '' === $claim_token
		     || strlen( $claim_token ) > 256
		     || ! preg_match( '/^[\x21-\x7e]+$/', $claim_token ) ) {
			return new WP_REST_Response(
				array( 'error' => 'invalid_claim_token' ),
				400
			);
		}

		$result = $this->api->exchange_claim_token( $claim_token );

		if ( is_wp_error( $result ) ) {
			$status = (int) $result->get_error_data( 'status' );
			// 404 = expired / replayed claim; 400 = malformed.
			$http   = in_array( $status, array( 400, 404 ), true ) ? $status : 502;
			return new WP_REST_Response(
				array( 'error' => 'claim_exchange_failed', 'detail' => $result->get_error_message() ),
				$http
			);
		}

		if ( empty( $result['site_token'] ) || empty( $result['site_id'] ) ) {
			return new WP_REST_Response(
				array( 'error' => 'claim_exchange_malformed' ),
				502
			);
		}

		update_option( 'xenarch_site_token', sanitize_text_field( (string) $result['site_token'] ) );
		update_option( 'xenarch_site_id',    sanitize_text_field( (string) $result['site_id'] ) );

		// Bust the gating-config cache so the gate picks up whatever the
		// dashboard already has set without waiting out the 60s TTL.
		delete_transient( Xenarch_Gate::GATING_CONFIG_TRANSIENT );

		return new WP_REST_Response( $this->snapshot(), 200 );
	}

	/**
	 * Disconnect the plugin from the platform. Wipes the site_token but
	 * leaves the bot detection log + browser-proof secret in place so
	 * reconnecting under the same identity doesn't reset learned state.
	 */
	public function handle_disconnect() {
		delete_option( 'xenarch_site_token' );
		delete_option( 'xenarch_site_id' );
		delete_transient( Xenarch_Gate::GATING_CONFIG_TRANSIENT );
		return new WP_REST_Response( $this->snapshot(), 200 );
	}

	// ------------------------------------------------------------------
	// XEN-380 / XEN-383 — thin-window proxies (read + write)
	// ------------------------------------------------------------------

	public function handle_get_site() {
		return $this->forward( $this->api->get_my_site() );
	}

	public function handle_put_gating( $request ) {
		$body = $request->get_json_params();
		return $this->forward( $this->api->put_my_site_gating(
			isset( $body['gating_enabled'] ) ? (bool) $body['gating_enabled'] : true,
			isset( $body['gated_categories'] ) && is_array( $body['gated_categories'] ) ? $body['gated_categories'] : array(),
			isset( $body['use_publisher_defaults'] ) ? (bool) $body['use_publisher_defaults'] : false
		) );
	}

	public function handle_put_pricing( $request ) {
		$body  = $request->get_json_params();
		$rules = isset( $body['rules'] ) && is_array( $body['rules'] ) ? $body['rules'] : array();
		return $this->forward( $this->api->put_my_site_pricing(
			isset( $body['default_price_usd'] ) ? (float) $body['default_price_usd'] : 0.003,
			$rules,
			isset( $body['default_billing_scope'] ) ? (string) $body['default_billing_scope'] : 'page'
		) );
	}

	public function handle_get_stats() {
		return $this->forward( $this->api->get_my_site_stats() );
	}

	public function handle_get_transactions( $request ) {
		$params = array(
			'period'   => $request->get_param( 'period' )   ?: 'all',
			'status'   => $request->get_param( 'status' )   ?: 'all',
			'page'     => $request->get_param( 'page' )     ?: 1,
			'per_page' => $request->get_param( 'per_page' ) ?: 25,
		);
		return $this->forward( $this->api->get_my_site_transactions( $params ) );
	}

	public function handle_get_category_breakdown() {
		return $this->forward( $this->api->get_my_site_category_breakdown() );
	}

	/**
	 * Standard "forward a platform call result to the React caller" wrapper.
	 * Preserves the platform's HTTP status where useful (401 / 404 / 502).
	 */
	private function forward( $result ) {
		if ( is_wp_error( $result ) ) {
			$status = (int) $result->get_error_data( 'status' );
			if ( $status < 400 || $status > 599 ) {
				$status = 502;
			}
			return new WP_REST_Response(
				array( 'error' => $result->get_error_code(), 'detail' => $result->get_error_message() ),
				$status
			);
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Plugin-local snapshot — only what the React app needs to render
	 * the onboarding-vs-main decision and the read-only status fields.
	 * The platform owns everything else; the admin tabs fetch their own
	 * data live.
	 */
	private function snapshot() {
		$site_id    = get_option( 'xenarch_site_id', '' );
		$site_token = get_option( 'xenarch_site_token', '' );

		return array(
			'site_id'             => (string) $site_id,
			'site_token'          => (string) $site_token,
			'domain'              => wp_parse_url( get_site_url(), PHP_URL_HOST ),
			'has_site'            => ! empty( $site_token ),
			'bot_signature_count' => count( Xenarch_Bot_Detect::get_signatures() )
				+ count( Xenarch_Bot_Detect::get_fetcher_signatures() ),
			'pay_json_url'        => get_site_url() . '/.well-known/pay.json',
			'xenarch_md_url'      => get_site_url() . '/.well-known/xenarch.md',
		);
	}
}

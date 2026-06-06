<?php
/**
 * Xenarch platform API client.
 *
 * Post-XEN-380 the plugin is a thin window into the platform — the only
 * things this client does are:
 *
 *   1. talk to the gate hot-path (create gate, verify on-chain payment,
 *      read dashboard-managed gating config),
 *   2. complete the claim handshake that boots a fresh install
 *      (``exchange_claim_token``),
 *   3. read a few public config knobs (``get_config``) that the admin UI
 *      occasionally needs.
 *
 * All Bearer-API-key endpoints are gone — pricing, payouts, identity
 * wallet linking, transactions, balances are managed in the dashboard
 * and the plugin reads/writes them through site_token-authed surfaces
 * (not in this rewrite — Phase B).
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenarch_Api {

	/** @var string */
	private $base_url;

	public function __construct() {
		$this->base_url = apply_filters( 'xenarch_api_base', 'https://api.xenarch.dev' );
	}

	/**
	 * Public-config endpoint. Today the plugin doesn't actively use any
	 * of the values it returns, but the call is harmless and the hook
	 * is kept so other code (e.g. status checks) can ping it cheaply.
	 *
	 * @return array|WP_Error
	 */
	public function get_config() {
		return $this->get( '/v1/config' );
	}

	/**
	 * Verify an on-chain payment for a gate. Stateless — the platform
	 * re-derives everything from the tx hash on each call.
	 *
	 * @param string $gate_id Gate UUID returned in the 402 envelope.
	 * @param string $tx_hash 0x-prefixed transaction hash.
	 * @return array|WP_Error
	 */
	public function verify_payment( $gate_id, $tx_hash ) {
		$site_token = get_option( 'xenarch_site_token', '' );
		return $this->post(
			'/v1/gates/' . urlencode( $gate_id ) . '/verify',
			array( 'tx_hash' => $tx_hash ),
			array( 'X-Site-Token' => $site_token )
		);
	}

	/**
	 * Read the dashboard-managed gating state for this site. Returns
	 * ``{gating_enabled, gated_categories}``. Cached by the caller via
	 * a WP transient (see Xenarch_Gate::get_gating_config).
	 *
	 * @return array|WP_Error
	 */
	public function get_gating_config() {
		$site_token = get_option( 'xenarch_site_token', '' );
		if ( empty( $site_token ) ) {
			return new WP_Error( 'no_site_token', 'site token not configured' );
		}
		return $this->get(
			'/v1/sites/me/gating-config',
			array( 'X-Site-Token' => $site_token )
		);
	}

	/**
	 * Create a gate on the platform for a freshly detected bot request.
	 * Returns the full x402 envelope (with ``accepts`` + ``facilitators``)
	 * which the plugin echoes back as the 402 body.
	 *
	 * @param string $url              Request path being protected.
	 * @param string $detection_method Detection label for the audit trail.
	 * @return array|WP_Error
	 */
	public function create_gate( $url, $detection_method = 'ua_match' ) {
		$site_token = get_option( 'xenarch_site_token', '' );
		return $this->post(
			'/v1/gates',
			array(
				'url'              => $url,
				'detection_method' => $detection_method,
			),
			array( 'X-Site-Token' => $site_token ),
			'POST',
			true  // allow 402 as success
		);
	}

	/**
	 * Swap a one-time claim_token (issued by dash.xenarch.dev) for the
	 * long-lived ``site_token``. Server-to-server only — the claim_token
	 * never lives anywhere except its single trip through the redirect
	 * URL and this request.
	 *
	 * @param string $claim_token
	 * @return array|WP_Error  ``{site_id, site_token, domain, integration_type}``
	 */
	public function exchange_claim_token( $claim_token ) {
		return $this->post(
			'/v1/site-claims/' . urlencode( $claim_token ) . '/exchange',
			array()
		);
	}

	// ------------------------------------------------------------------
	// XEN-380 / XEN-383 — thin-window mirror of /me/sites/{id}/*
	// All X-Site-Token authed.
	// ------------------------------------------------------------------

	private function site_token_headers() {
		$site_token = get_option( 'xenarch_site_token', '' );
		return array( 'X-Site-Token' => $site_token );
	}

	/**
	 * Push a batch of bot-detection rows from wp_xenarch_bot_log to the
	 * platform. XEN-394 — lets the dashboard /bots Cross-site activity
	 * panel aggregate across all of a publisher's sites.
	 *
	 * @param array $detections array of associative arrays each shaped
	 *                          {signature, category, company, first_seen,
	 *                           last_seen, hit_count} (last_seen/first_seen
	 *                           as ISO-8601 UTC strings, hit_count as int).
	 * @return array|WP_Error platform response or error.
	 */
	public function post_bot_detections( $detections ) {
		$site_token = get_option( 'xenarch_site_token', '' );
		if ( empty( $site_token ) ) {
			return new WP_Error( 'no_site_token', 'site token not configured' );
		}
		if ( ! is_array( $detections ) || empty( $detections ) ) {
			return array( 'received' => 0, 'upserted' => 0 );
		}
		// XEN-394 v2: fire-and-forget. Called inline on every bot
		// detection — adding network round-trip latency to bot page
		// loads is unacceptable, and we don't care about the response
		// (platform is canonical; failures show up as missing
		// detections, not as duplicates).
		$url     = trailingslashit( $this->base_url ) . ltrim( '/v1/sites/me/bot-detections', '/' );
		$body    = wp_json_encode( array( 'detections' => array_values( $detections ) ) );
		$headers = array_merge(
			array( 'Content-Type' => 'application/json' ),
			$this->site_token_headers()
		);
		wp_remote_post( $url, array(
			'method'   => 'POST',
			'headers'  => $headers,
			'body'     => $body,
			'timeout'  => 0.01,   // open the connection, don't wait
			'blocking' => false,  // return immediately
		) );
		return array( 'received' => count( $detections ), 'upserted' => null );
	}

	/** Full site detail (mirror of dashboard /sites/[id]). */
	public function get_my_site() {
		$site_token = get_option( 'xenarch_site_token', '' );
		if ( empty( $site_token ) ) {
			return new WP_Error( 'no_site_token', 'site token not configured' );
		}
		return $this->get( '/v1/sites/me', $this->site_token_headers() );
	}

	/** PUT gating — full replace of gating_enabled + gated_categories + use_publisher_defaults. */
	public function put_my_site_gating( $gating_enabled, $gated_categories, $use_publisher_defaults ) {
		return $this->post(
			'/v1/sites/me/gating',
			array(
				'gating_enabled'         => (bool) $gating_enabled,
				'gated_categories'       => $gated_categories,
				'use_publisher_defaults' => (bool) $use_publisher_defaults,
			),
			$this->site_token_headers(),
			'PUT'
		);
	}

	/** PUT pricing — default + per-path rules. */
	public function put_my_site_pricing( $default_price_usd, $rules, $default_billing_scope = 'page' ) {
		return $this->post(
			'/v1/sites/me/pricing',
			array(
				'default_price_usd'     => (float) $default_price_usd,
				'default_billing_scope' => $default_billing_scope,
				'rules'                 => $rules,
			),
			$this->site_token_headers(),
			'PUT'
		);
	}

	/** Today / month / all-time stats. */
	public function get_my_site_stats() {
		return $this->get( '/v1/sites/me/stats', $this->site_token_headers() );
	}

	/** Paginated transactions feed. */
	public function get_my_site_transactions( $params = array() ) {
		$qs = http_build_query( $params );
		return $this->get(
			'/v1/sites/me/transactions' . ( $qs ? '?' . $qs : '' ),
			$this->site_token_headers()
		);
	}

	/** Earnings grouped by detected bot category. */
	public function get_my_site_category_breakdown() {
		return $this->get(
			'/v1/sites/me/category-breakdown',
			$this->site_token_headers()
		);
	}

	/**
	 * XEN-435 P4: the merchant identity's linked wallets, so the admin can
	 * pick which one receives gate revenue. Returns
	 * ``{wallets:[{address, eligible_at, eligible, is_default}]}``.
	 *
	 * @return array|WP_Error
	 */
	public function get_my_site_wallets() {
		return $this->get( '/v1/sites/me/wallets', $this->site_token_headers() );
	}

	/**
	 * XEN-435 P4: set the gate's receiving wallet (the identity default). The
	 * platform only accepts an already-eligible linked wallet.
	 *
	 * @param string $wallet 0x-prefixed address.
	 * @return array|WP_Error
	 */
	public function put_my_site_payout_wallet( $wallet ) {
		return $this->post(
			'/v1/sites/me/payout-wallet',
			array( 'wallet' => (string) $wallet ),
			$this->site_token_headers(),
			'PUT'
		);
	}

	// ------------------------------------------------------------------
	// Internal HTTP helpers
	// ------------------------------------------------------------------

	private function post( $endpoint, $body = array(), $headers = array(), $method = 'POST', $allow_402 = false ) {
		$headers['Content-Type'] = 'application/json';
		$response = wp_remote_post(
			$this->base_url . $endpoint,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);
		return $this->handle_response( $response, $allow_402 );
	}

	private function get( $endpoint, $headers = array() ) {
		$response = wp_remote_get(
			$this->base_url . $endpoint,
			array(
				'headers' => $headers,
				'timeout' => 30,
			)
		);
		return $this->handle_response( $response );
	}

	private function handle_response( $response, $allow_402 = false ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $data ) ? $data : array();
		}
		if ( $allow_402 && 402 === $code && is_array( $data ) ) {
			return $data;
		}

		$message = '';
		if ( is_array( $data ) ) {
			$message = isset( $data['message'] ) ? $data['message']
				: ( isset( $data['detail'] ) ? ( is_array( $data['detail'] ) ? wp_json_encode( $data['detail'] ) : (string) $data['detail'] )
				: '' );
		}
		if ( '' === $message ) {
			$message = 'API call failed (HTTP ' . (int) $code . ')';
		}

		return new WP_Error(
			'xenarch_api_error',
			$message,
			array( 'status' => $code )
		);
	}
}

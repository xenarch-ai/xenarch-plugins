<?php
/**
 * Xenarch API client.
 *
 * Wraps all communication with the xenarch.bot REST API using the
 * WordPress HTTP API (wp_remote_post / wp_remote_get).
 *
 * @package Xenarch
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Api
 */
class Xenarch_Api {

	/**
	 * Base URL for the Xenarch API.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		/**
		 * Filter the Xenarch API base URL.
		 *
		 * @param string $base_url Default API base URL.
		 */
		$this->base_url = apply_filters( 'xenarch_api_base', 'https://xenarch.bot' );
	}

	/**
	 * Register a new publisher account.
	 *
	 * @param string $email    Publisher email address.
	 * @param string $password Publisher password.
	 * @return array|WP_Error  Response body array on success, WP_Error on failure.
	 */
	public function register( $email, $password ) {
		return $this->post(
			'/v1/publishers',
			array(
				'email'    => $email,
				'password' => $password,
			)
		);
	}

	/**
	 * Add a site for the authenticated publisher.
	 *
	 * @param string $domain Site domain (e.g. "myblog.com").
	 * @return array|WP_Error
	 */
	public function add_site( $domain ) {
		return $this->post(
			'/v1/sites',
			array( 'domain' => $domain ),
			$this->auth_headers()
		);
	}

	/**
	 * List sites for the authenticated publisher.
	 *
	 * @return array|WP_Error
	 */
	public function list_sites() {
		return $this->get( '/v1/sites', $this->auth_headers() );
	}

	/**
	 * Update pricing rules for a site.
	 *
	 * @param string $site_id          Site UUID.
	 * @param float  $default_price    Default price in USD.
	 * @param array  $rules            Optional pricing rules array.
	 * @return array|WP_Error
	 */
	public function update_pricing( $site_id, $default_price, $rules = array() ) {
		$body = array( 'default_price_usd' => (float) $default_price );

		if ( ! empty( $rules ) ) {
			$body['rules'] = $rules;
		}

		return $this->post(
			'/v1/sites/' . urlencode( $site_id ) . '/pricing',
			$body,
			$this->auth_headers(),
			'PUT'
		);
	}

	/**
	 * Update payout wallet for the authenticated publisher.
	 *
	 * @param string $wallet   Wallet address (0x...).
	 * @param string $password Publisher password for confirmation.
	 * @return array|WP_Error
	 */
	public function update_payout( $wallet, $password ) {
		$headers = $this->auth_headers();
		$headers['X-Confirm-Password'] = $password;

		return $this->post(
			'/v1/publishers/me/payout',
			array(
				'wallet'  => $wallet,
				'network' => 'base',
			),
			$headers,
			'PUT'
		);
	}

	/**
	 * Get publisher profile.
	 *
	 * @return array|WP_Error
	 */
	public function get_profile() {
		return $this->get( '/v1/publishers/me', $this->auth_headers() );
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * Build authorisation headers from stored API key.
	 *
	 * @return array
	 */
	private function auth_headers() {
		$api_key = get_option( 'xenarch_api_key', '' );

		return array(
			'Authorization' => 'Bearer ' . $api_key,
		);
	}

	/**
	 * Perform a POST (or PUT) request.
	 *
	 * @param string $endpoint API endpoint path (e.g. "/v1/publishers").
	 * @param array  $body     Request body (will be JSON-encoded).
	 * @param array  $headers  Additional headers.
	 * @param string $method   HTTP method (POST or PUT).
	 * @return array|WP_Error
	 */
	private function post( $endpoint, $body = array(), $headers = array(), $method = 'POST' ) {
		$url = $this->base_url . $endpoint;

		$headers['Content-Type'] = 'application/json';

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		);

		$response = wp_remote_post( $url, $args );

		return $this->handle_response( $response );
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string $endpoint API endpoint path.
	 * @param array  $headers  Additional headers.
	 * @return array|WP_Error
	 */
	private function get( $endpoint, $headers = array() ) {
		$url = $this->base_url . $endpoint;

		$args = array(
			'headers' => $headers,
			'timeout' => 30,
		);

		$response = wp_remote_get( $url, $args );

		return $this->handle_response( $response );
	}

	/**
	 * Parse and validate an API response.
	 *
	 * @param array|WP_Error $response wp_remote_* response.
	 * @return array|WP_Error
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $data ) ? $data : array();
		}

		$message = isset( $data['message'] ) ? $data['message'] : 'Unknown API error';
		$error   = isset( $data['error'] ) ? $data['error'] : 'api_error';

		return new WP_Error(
			'xenarch_' . $error,
			$message,
			array( 'status' => $code )
		);
	}
}

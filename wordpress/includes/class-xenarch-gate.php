<?php
/**
 * Xenarch server-side gate.
 *
 * Intercepts requests from detected AI bots at the PHP level and returns
 * HTTP 402 + JSON gate details. Bots don't execute JS, so l.js alone
 * cannot gate them — this class handles the server-side equivalent.
 *
 * @package Xenarch
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Gate
 */
class Xenarch_Gate {

	/**
	 * API client instance.
	 *
	 * @var Xenarch_Api
	 */
	private $api;

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		$this->api = new Xenarch_Api();

		// Priority 1: run before anything else renders.
		add_action( 'template_redirect', array( $this, 'maybe_gate_request' ), 1 );
	}

	/**
	 * Check incoming request and return 402 for detected bots.
	 */
	public function maybe_gate_request() {
		// No site token configured — plugin not set up yet.
		$site_token = get_option( 'xenarch_site_token', '' );
		if ( empty( $site_token ) ) {
			return;
		}

		// Never gate admin pages or logged-in users.
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		// Never gate /.well-known paths (discovery docs).
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( 0 === strpos( $request_uri, '/.well-known/' ) ) {
			return;
		}

		// If bot has a Bearer token, let it through (paid agent returning).
		$auth_header = $this->get_authorization_header();
		if ( ! empty( $auth_header ) && 0 === stripos( $auth_header, 'Bearer ' ) ) {
			return;
		}

		// Run full bot detection (UA matching + header scoring).
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$headers    = self::get_request_headers();
		$detection  = Xenarch_Bot_Detect::detect_full( $user_agent, $headers );

		if ( ! $detection['is_bot'] ) {
			return; // Not a bot — normal WordPress rendering, l.js handles browsers.
		}

		// Bot detected — create or retrieve a gate.
		$gate = $this->get_or_create_gate( $request_uri, $detection['method'] );

		if ( is_wp_error( $gate ) || empty( $gate ) ) {
			// API error — fall through to normal rendering rather than breaking the site.
			return;
		}

		// Build the site URL for discovery document links.
		$site_url = get_site_url();

		// Add discovery URLs to the response.
		$gate['instructions_url'] = $site_url . '/.well-known/xenarch.md';
		$gate['pay_json_url']     = $site_url . '/.well-known/pay.json';

		// Ensure xenarch flag is present.
		if ( ! isset( $gate['xenarch'] ) ) {
			$gate['xenarch'] = true;
		}

		// Return HTTP 402 + JSON.
		status_header( 402 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Xenarch-Bot: ' . $detection['method'] );
		echo wp_json_encode( $gate );
		exit;
	}

	/**
	 * Get request headers relevant for bot detection.
	 *
	 * @return array Associative array of header name => value.
	 */
	private static function get_request_headers() {
		$headers = array();
		$map     = array(
			'HTTP_ACCEPT'          => 'Accept',
			'HTTP_ACCEPT_LANGUAGE' => 'Accept-Language',
			'HTTP_ACCEPT_ENCODING' => 'Accept-Encoding',
			'HTTP_SEC_FETCH_MODE'  => 'Sec-Fetch-Mode',
			'HTTP_SEC_FETCH_DEST'  => 'Sec-Fetch-Dest',
			'HTTP_SEC_FETCH_SITE'  => 'Sec-Fetch-Site',
			'HTTP_SEC_CH_UA'       => 'Sec-Ch-Ua',
		);

		foreach ( $map as $server_key => $header_name ) {
			if ( isset( $_SERVER[ $server_key ] ) ) {
				$headers[ $header_name ] = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
			}
		}

		return $headers;
	}

	/**
	 * Get or create a gate for the given path, with transient caching.
	 *
	 * @param string $request_uri      The request URI path.
	 * @param string $detection_method The detection method that triggered gating.
	 * @return array|WP_Error Gate data array or WP_Error on failure.
	 */
	private function get_or_create_gate( $request_uri, $detection_method = 'ua_match' ) {
		// Parse to just the path (strip query string).
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( empty( $path ) ) {
			$path = '/';
		}

		// Check transient cache (30 min TTL).
		$cache_key = 'xenarch_gate_' . md5( $path );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Create gate via API.
		$result = $this->api->create_gate( $path, $detection_method );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Cache for 30 minutes.
		set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Get the Authorization header from the request.
	 *
	 * WordPress/Apache don't always pass Authorization to PHP,
	 * so we check multiple sources.
	 *
	 * @return string|null
	 */
	private function get_authorization_header() {
		// Check standard header.
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		// Apache with mod_rewrite.
		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		// Fallback: getallheaders() (Apache).
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( isset( $headers['Authorization'] ) ) {
				return $headers['Authorization'];
			}
			// Some servers lowercase it.
			if ( isset( $headers['authorization'] ) ) {
				return $headers['authorization'];
			}
		}

		return null;
	}
}

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
		add_filter( 'rest_pre_dispatch', array( $this, 'maybe_gate_rest_request' ), 1, 3 );
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
		if ( 0 === strpos( $request_uri, '/.well-known/' ) || '/pay.json' === $request_uri ) {
			return;
		}

		// If the request presents a Xenarch-looking access token, let it through.
		$auth_header = $this->get_authorization_header();
		if ( Xenarch_Access_Token::has_valid_bearer_format( $auth_header ) ) {
			return;
		}

		// Check if a pricing rule marks this path as FREE (price_usd = "0").
		if ( $this->is_free_path( $request_uri ) ) {
			return;
		}

		// Run full non-human traffic detection.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$headers    = self::get_request_headers();
		$detection  = Xenarch_Bot_Detect::detect_full( $user_agent, $headers, $this->get_request_context( $request_uri, $user_agent ) );
		$this->record_detection_event( $request_uri, $detection );

		// Allow unknown non-browser traffic through if toggle is off (webhooks etc.).
		if ( 'unknown_non_browser' === $detection['traffic_class']
		     && get_option( 'xenarch_gate_unknown_traffic', '1' ) !== '1' ) {
			return;
		}

		if ( 'allow' === $detection['decision'] ) {
			return;
		}

		if ( 'challenge' === $detection['decision'] ) {
			$this->render_browser_challenge( $request_uri, $user_agent, $detection );
			return;
		}

		$this->render_block_response( $request_uri, $detection );
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
			'HTTP_SEC_FETCH_USER'  => 'Sec-Fetch-User',
			'HTTP_SEC_CH_UA'       => 'Sec-Ch-Ua',
			'HTTP_SEC_CH_UA_MOBILE' => 'Sec-CH-UA-Mobile',
			'HTTP_SEC_CH_UA_PLATFORM' => 'Sec-CH-UA-Platform',
			'HTTP_UPGRADE_INSECURE_REQUESTS' => 'Upgrade-Insecure-Requests',
			'HTTP_REFERER'         => 'Referer',
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
	 * Build extra request context for classification.
	 *
	 * @param string $request_uri Request URI.
	 * @param string $user_agent  User-Agent header.
	 * @return array
	 */
	private function get_request_context( $request_uri, $user_agent ) {
		$cookie_value = isset( $_COOKIE[ Xenarch_Browser_Proof::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ Xenarch_Browser_Proof::COOKIE_NAME ] ) ) : '';

		return array(
			'request_method'      => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET',
			'has_cookies'         => ! empty( $_COOKIE ),
			'browser_proof_valid' => Xenarch_Browser_Proof::validate_cookie_value( $cookie_value, $user_agent ),
			'request_path'        => $request_uri,
			'is_feed'             => function_exists( 'is_feed' ) ? is_feed() : false,
			'is_preview'          => function_exists( 'is_preview' ) ? is_preview() : false,
		);
	}

	/**
	 * Render a browser proof challenge page.
	 *
	 * @param string $request_uri Request URI.
	 * @param string $user_agent  User-Agent string.
	 * @param array  $detection   Detection result.
	 * @return void
	 */
	private function render_browser_challenge( $request_uri, $user_agent, $detection ) {
		$path         = wp_parse_url( $request_uri, PHP_URL_PATH );
		$path         = empty( $path ) ? '/' : $path;
		$cookie_value = Xenarch_Browser_Proof::issue_cookie_value( $user_agent );

		status_header( 403 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Xenarch-Bot: ' . $detection['method'] );
		header( 'X-Xenarch-Decision: challenge' );
		echo Xenarch_Gate_Response::build_challenge_html( $path, $cookie_value );
		exit;
	}

	/**
	 * Render a blocking payment gate response.
	 *
	 * @param string $request_uri Request URI.
	 * @param array  $detection   Detection result.
	 * @return void
	 */
	private function render_block_response( $request_uri, $detection ) {
		$gate = $this->get_or_create_gate( $request_uri, $detection['method'] );

		if ( is_wp_error( $gate ) || empty( $gate ) ) {
			$path = wp_parse_url( $request_uri, PHP_URL_PATH );
			$path = empty( $path ) ? '/' : $path;
			$gate = Xenarch_Gate_Response::build_fallback_gate_payload( $path, $detection['method'] );
		} else {
			if ( ! isset( $gate['xenarch'] ) ) {
				$gate['xenarch'] = true;
			}
		}

		$gate = $this->enrich_gate_payload( $gate );

		status_header( 402 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Xenarch-Bot: ' . $detection['method'] );
		header( 'X-Xenarch-Decision: block' );
		foreach ( $this->get_discovery_headers() as $name => $value ) {
			header( $name . ': ' . $value );
		}
		echo wp_json_encode( $gate );
		exit;
	}

	/**
	 * Apply the same gating policy to REST requests.
	 *
	 * @param mixed           $response Current response.
	 * @param WP_REST_Server  $server   REST server.
	 * @param WP_REST_Request $request  REST request.
	 * @return mixed
	 */
	public function maybe_gate_rest_request( $response, $server, $request ) {
		if ( ! class_exists( 'WP_REST_Response' ) || ! empty( $response ) ) {
			return $response;
		}

		$site_token = get_option( 'xenarch_site_token', '' );
		if ( empty( $site_token ) || is_user_logged_in() || is_admin() ) {
			return $response;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		if ( 0 === strpos( $request_uri, '/.well-known/' ) || '/pay.json' === $request_uri ) {
			return $response;
		}

		$auth_header = $this->get_authorization_header();
		if ( Xenarch_Access_Token::has_valid_bearer_format( $auth_header ) ) {
			return $response;
		}

		// Check if a pricing rule marks this path as FREE.
		if ( $this->is_free_path( $request_uri ) ) {
			return $response;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$headers    = self::get_request_headers();
		$detection  = Xenarch_Bot_Detect::detect_full( $user_agent, $headers, $this->get_request_context( $request_uri, $user_agent ) );
		$this->record_detection_event( $request_uri, $detection );

		// Allow unknown non-browser traffic through if toggle is off (webhooks etc.).
		if ( 'unknown_non_browser' === $detection['traffic_class']
		     && get_option( 'xenarch_gate_unknown_traffic', '1' ) !== '1' ) {
			return $response;
		}

		if ( 'allow' === $detection['decision'] ) {
			return $response;
		}

		if ( 'challenge' === $detection['decision'] ) {
			return new WP_REST_Response(
				array(
					'xenarch'  => true,
					'decision' => 'challenge',
					'message'  => 'Browser verification required before accessing this resource.',
				),
				403,
				array(
					'Cache-Control'     => 'no-store, private',
					'X-Xenarch-Bot'     => $detection['method'],
					'X-Xenarch-Decision' => 'challenge',
				)
			);
		}

		$gate = $this->get_or_create_gate( $request_uri, $detection['method'] );
		if ( is_wp_error( $gate ) || empty( $gate ) ) {
			$path = wp_parse_url( $request_uri, PHP_URL_PATH );
			$path = empty( $path ) ? '/' : $path;
			$gate = Xenarch_Gate_Response::build_fallback_gate_payload( $path, $detection['method'] );
		}

		$gate = $this->enrich_gate_payload( $gate );

		return new WP_REST_Response(
			$gate,
			402,
			array_merge(
				array(
					'Cache-Control'      => 'no-store, private',
					'X-Xenarch-Bot'      => $detection['method'],
					'X-Xenarch-Decision' => 'block',
				),
				$this->get_discovery_headers()
			)
		);
	}

	/**
	 * Build HTTP headers that help agents discover payment info.
	 *
	 * @return array Header name => value pairs.
	 */
	private function get_discovery_headers() {
		$site_url     = get_site_url();
		$pay_json_url = $site_url . '/.well-known/pay.json';
		$xenarch_md   = $site_url . '/.well-known/xenarch.md';

		return array(
			'Link'        => '<' . $pay_json_url . '>; rel="payment-terms", <' . $xenarch_md . '>; rel="payment-instructions"',
			'X-Pay-Json'  => $pay_json_url,
			'X-Xenarch'   => 'payment-required; pay_json="' . $pay_json_url . '"',
		);
	}

	/**
	 * Enrich a gate payload with discovery URLs and a human/LLM-readable message.
	 *
	 * @param array $gate Gate payload.
	 * @return array Enriched gate payload.
	 */
	private function enrich_gate_payload( $gate ) {
		$site_url     = get_site_url();
		$pay_json_url = $site_url . '/.well-known/pay.json';

		$gate['pay_json_url']     = $pay_json_url;
		$gate['instructions_url'] = $site_url . '/.well-known/xenarch.md';
		$gate['message']          = 'Payment required. Fetch ' . $pay_json_url . ' for pricing, payment address, and integration tools. Full instructions at ' . $site_url . '/.well-known/xenarch.md';

		return $gate;
	}

	/**
	 * Record a structured detection event for observability hooks.
	 *
	 * @param string $request_uri Request URI.
	 * @param array  $detection   Detection result.
	 * @return void
	 */
	private function record_detection_event( $request_uri, $detection ) {
		$event = array(
			'request_uri'   => $request_uri,
			'decision'      => isset( $detection['decision'] ) ? $detection['decision'] : '',
			'traffic_class' => isset( $detection['traffic_class'] ) ? $detection['traffic_class'] : '',
			'method'        => isset( $detection['method'] ) ? $detection['method'] : '',
			'score'         => isset( $detection['score'] ) ? $detection['score'] : 0,
			'signal'        => isset( $detection['signal'] ) ? $detection['signal'] : '',
		);

		if ( function_exists( 'do_action' ) ) {
			do_action( 'xenarch_detection_event', $event );
		}

		if ( function_exists( 'apply_filters' ) && apply_filters( 'xenarch_log_detection_events', false, $event ) ) {
			error_log( 'xenarch_detection_event ' . wp_json_encode( $event ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Check if a request path matches a FREE pricing rule (price_usd = "0").
	 *
	 * @param string $request_uri Request URI.
	 * @return bool True if path is free.
	 */
	private function is_free_path( $request_uri ) {
		$matched = $this->match_pricing_rule( $request_uri );
		return null !== $matched && '0' === (string) $matched;
	}

	/**
	 * Match a request path against pricing rules. Returns the matched
	 * price_usd string, or null if no rule matches.
	 *
	 * @param string $request_uri Request URI.
	 * @return string|null Matched price_usd or null.
	 */
	private function match_pricing_rule( $request_uri ) {
		$path  = wp_parse_url( $request_uri, PHP_URL_PATH );
		$path  = empty( $path ) ? '/' : $path;
		$rules = json_decode( get_option( 'xenarch_pricing_rules', '[]' ), true );

		if ( ! is_array( $rules ) ) {
			return null;
		}

		foreach ( $rules as $rule ) {
			if ( empty( $rule['path_contains'] ) || ! isset( $rule['price_usd'] ) ) {
				continue;
			}
			if ( false !== strpos( $path, $rule['path_contains'] ) ) {
				return (string) $rule['price_usd'];
			}
		}

		return null;
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
				return sanitize_text_field( $headers['Authorization'] );
			}
			// Some servers lowercase it.
			if ( isset( $headers['authorization'] ) ) {
				return sanitize_text_field( $headers['authorization'] );
			}
		}

		return null;
	}
}

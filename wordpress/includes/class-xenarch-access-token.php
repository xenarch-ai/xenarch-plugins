<?php
/**
 * Xenarch access token verification.
 *
 * Validates bearer tokens against the Xenarch platform API.
 * Verified tokens are cached in WordPress transients to avoid
 * hitting the API on every request.
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Access_Token
 */
class Xenarch_Access_Token {

	/**
	 * Cache prefix for verified tokens.
	 */
	const CACHE_PREFIX = 'xenarch_token_';

	/**
	 * Cache TTL in seconds (5 minutes).
	 */
	const CACHE_TTL = 300;

	/**
	 * Determine whether an Authorization header contains a JWT-like bearer token.
	 *
	 * Format check only — does NOT verify the token is valid.
	 * Use verify_bearer_token() for full verification.
	 *
	 * @param string|null $auth_header Authorization header value.
	 * @return bool
	 */
	public static function has_valid_bearer_format( $auth_header ) {
		if ( empty( $auth_header ) || 0 !== stripos( $auth_header, 'Bearer ' ) ) {
			return false;
		}

		$token = trim( substr( $auth_header, 7 ) );
		if ( empty( $token ) ) {
			return false;
		}

		return 1 === preg_match( '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)?$/', $token );
	}

	/**
	 * Extract the bearer token string from an Authorization header.
	 *
	 * @param string|null $auth_header Authorization header value.
	 * @return string|null Token string or null.
	 */
	public static function extract_token( $auth_header ) {
		if ( empty( $auth_header ) || 0 !== stripos( $auth_header, 'Bearer ' ) ) {
			return null;
		}
		$token = trim( substr( $auth_header, 7 ) );
		return empty( $token ) ? null : $token;
	}

	/**
	 * Verify a bearer token is valid (paid gate receipt).
	 *
	 * Checks the transient cache first. If not cached, verifies
	 * against the Xenarch platform API and caches the result.
	 *
	 * @param string $token The bearer token string.
	 * @return bool True if the token is a verified paid access token.
	 */
	public static function verify_token( $token, $url = '' ) {
		if ( empty( $token ) ) {
			return false;
		}

		// Check format first.
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)?$/', $token ) ) {
			return false;
		}

		// Check transient cache (key includes URL for per-page/path scoping).
		$cache_key = self::CACHE_PREFIX . md5( $token . '|' . $url );
		$cached    = get_transient( $cache_key );

		if ( 'valid' === $cached ) {
			return true;
		}
		if ( 'invalid' === $cached ) {
			return false;
		}

		// Verify against platform API.
		$site_token = get_option( 'xenarch_site_token', '' );
		if ( empty( $site_token ) ) {
			// No site token = can't verify. Reject the token.
			return false;
		}

		$api    = new Xenarch_Api();
		$result = $api->verify_access_token( $token, $url );

		if ( is_wp_error( $result ) ) {
			// API unreachable — fail open to avoid blocking legitimate paid requests.
			// Cache as valid for a short time to reduce API pressure.
			set_transient( $cache_key, 'valid', 60 );
			return true;
		}

		$is_valid = ! empty( $result['valid'] );
		set_transient( $cache_key, $is_valid ? 'valid' : 'invalid', self::CACHE_TTL );

		return $is_valid;
	}
}

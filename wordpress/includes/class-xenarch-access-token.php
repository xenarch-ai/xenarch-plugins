<?php
/**
 * Xenarch access token helpers.
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
	 * Determine whether an Authorization header contains a JWT-like bearer token.
	 *
	 * This only validates the token format. Cryptographic verification happens
	 * in the actual Xenarch verification layer outside this plugin.
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

		return 1 === preg_match( '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token );
	}
}

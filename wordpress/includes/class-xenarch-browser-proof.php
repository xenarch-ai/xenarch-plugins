<?php
/**
 * Xenarch browser proof helpers.
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Browser_Proof
 */
class Xenarch_Browser_Proof {

	/**
	 * Cookie name used to store browser proof.
	 */
	const COOKIE_NAME = 'xenarch_browser_proof';

	/**
	 * Secret option name.
	 */
	const SECRET_OPTION = 'xenarch_browser_proof_secret';

	/**
	 * Default proof TTL in seconds.
	 */
	const DEFAULT_TTL = 900;

	/**
	 * Issue a signed cookie value for the provided user agent and issue time.
	 *
	 * @param string   $user_agent User-Agent string.
	 * @param int|null $issued_at  Optional issue timestamp.
	 * @return string
	 */
	public static function issue_cookie_value( $user_agent, $issued_at = null ) {
		$issued_at = $issued_at ? (int) $issued_at : time();
		$secret    = self::get_secret();

		if ( empty( $secret ) ) {
			return '';
		}
		$payload   = $issued_at . '|' . hash_hmac( 'sha256', $issued_at . '|' . $user_agent, $secret );

		return base64_encode( $payload ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Validate a browser proof cookie value.
	 *
	 * @param string   $cookie_value Raw cookie value.
	 * @param string   $user_agent   Current request user agent.
	 * @param int|null $current_time Optional current timestamp.
	 * @return bool
	 */
	public static function validate_cookie_value( $cookie_value, $user_agent, $current_time = null ) {
		if ( empty( $cookie_value ) || empty( $user_agent ) ) {
			return false;
		}

		$current_time = $current_time ? (int) $current_time : time();
		$secret       = self::get_secret();

		if ( empty( $secret ) ) {
			return false;
		}
		$decoded      = base64_decode( $cookie_value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			return false;
		}

		$parts = explode( '|', $decoded );
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$issued_at = (int) $parts[0];
		$signature = $parts[1];

		if ( $issued_at <= 0 || $issued_at + self::DEFAULT_TTL < $current_time ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $issued_at . '|' . $user_agent, $secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Get secret for signing proof cookies.
	 *
	 * @return string
	 */
	private static function get_secret() {
		if ( defined( 'XENARCH_BROWSER_PROOF_SECRET' ) && XENARCH_BROWSER_PROOF_SECRET ) {
			return (string) XENARCH_BROWSER_PROOF_SECRET;
		}

		if ( function_exists( 'get_option' ) ) {
			$secret = get_option( self::SECRET_OPTION, '' );
			if ( ! empty( $secret ) ) {
				return (string) $secret;
			}

			if ( function_exists( 'wp_generate_password' ) && function_exists( 'update_option' ) ) {
				$secret = wp_generate_password( 64, true, true );
				update_option( self::SECRET_OPTION, $secret );

				return (string) $secret;
			}
		}

		return '';
	}
}

<?php
/**
 * Xenarch payment proof verification.
 *
 * Validates an on-chain `tx_hash` against the platform for a given gate.
 * Verified hashes are cached in WordPress transients to avoid hitting the
 * platform on every replay of the same paid request.
 *
 * Replaces the old access-token (JWT) flow now that the platform does
 * stateless on-chain re-verification (post-XEN-179).
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Payment_Proof
 */
class Xenarch_Payment_Proof {

	/**
	 * Header name agents send with the payment hash.
	 */
	const HEADER_NAME = 'X-Payment-Tx';

	/**
	 * Cache prefix for verified hashes.
	 */
	const CACHE_PREFIX = 'xenarch_paid_';

	/**
	 * Cache TTL in seconds (5 minutes).
	 */
	const CACHE_TTL = 300;

	/**
	 * Read the X-Payment-Tx header from the current request.
	 *
	 * @return string|null Lower-case 0x-prefixed hash, or null if absent/malformed.
	 */
	public static function extract_tx_hash() {
		$candidates = array();

		if ( isset( $_SERVER['HTTP_X_PAYMENT_TX'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_PAYMENT_TX'] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( isset( $headers[ self::HEADER_NAME ] ) ) {
				$candidates[] = sanitize_text_field( $headers[ self::HEADER_NAME ] );
			}
			if ( isset( $headers['x-payment-tx'] ) ) {
				$candidates[] = sanitize_text_field( $headers['x-payment-tx'] );
			}
		}

		foreach ( $candidates as $value ) {
			$value = strtolower( trim( $value ) );
			if ( 1 === preg_match( '/^0x[0-9a-f]{64}$/', $value ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Verify a tx hash satisfies the gate for the given path.
	 *
	 * @param string $tx_hash Lower-case 0x-prefixed transaction hash.
	 * @param string $gate_id Gate UUID (typically returned by create_gate()).
	 * @return bool True if the platform confirms the payment.
	 */
	public static function verify( $tx_hash, $gate_id ) {
		if ( empty( $tx_hash ) || empty( $gate_id ) ) {
			return false;
		}

		$cache_key = self::CACHE_PREFIX . md5( $tx_hash . '|' . $gate_id );
		$cached    = get_transient( $cache_key );

		if ( 'valid' === $cached ) {
			return true;
		}
		if ( 'invalid' === $cached ) {
			return false;
		}

		$api    = new Xenarch_Api();
		$result = $api->verify_payment( $gate_id, $tx_hash );

		if ( is_wp_error( $result ) ) {
			// Platform unreachable — fail open briefly to avoid blocking legitimate
			// paid requests during platform downtime. Short cache to recover quickly.
			set_transient( $cache_key, 'valid', 60 );
			return true;
		}

		$is_valid = ! empty( $result['verified'] ) || ! empty( $result['valid'] );
		set_transient( $cache_key, $is_valid ? 'valid' : 'invalid', self::CACHE_TTL );

		return $is_valid;
	}
}

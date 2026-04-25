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
 * Canonical headers (must match the SDK middleware):
 *   X-Xenarch-Gate-Id  — UUID of the gate the agent paid for
 *   X-Xenarch-Tx-Hash  — 0x-prefixed Base USDC transferWithAuthorization hash
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
	 * Header carrying the on-chain transaction hash.
	 */
	const TX_HASH_HEADER = 'X-Xenarch-Tx-Hash';

	/**
	 * Header carrying the gate UUID the agent settled against.
	 */
	const GATE_ID_HEADER = 'X-Xenarch-Gate-Id';

	/**
	 * Cache prefix for verified hashes.
	 */
	const CACHE_PREFIX = 'xenarch_paid_';

	/**
	 * Cache TTL in seconds (5 minutes).
	 */
	const CACHE_TTL = 300;

	/**
	 * Read the canonical Xenarch payment headers from the current request.
	 *
	 * @return array{gate_id:string,tx_hash:string}|null
	 *         Both values present and well-formed, or null if either is missing.
	 */
	public static function extract_payment_proof() {
		$tx_hash = self::read_header( 'HTTP_X_XENARCH_TX_HASH', self::TX_HASH_HEADER );
		$gate_id = self::read_header( 'HTTP_X_XENARCH_GATE_ID', self::GATE_ID_HEADER );

		if ( null === $tx_hash || null === $gate_id ) {
			return null;
		}

		$tx_hash = strtolower( trim( $tx_hash ) );
		$gate_id = strtolower( trim( $gate_id ) );

		if ( 1 !== preg_match( '/^0x[0-9a-f]{64}$/', $tx_hash ) ) {
			return null;
		}
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $gate_id ) ) {
			return null;
		}

		return array(
			'gate_id' => $gate_id,
			'tx_hash' => $tx_hash,
		);
	}

	/**
	 * Read a header by both $_SERVER key and getallheaders() name (case-insensitive).
	 *
	 * @param string $server_key e.g. 'HTTP_X_XENARCH_TX_HASH'.
	 * @param string $header_name e.g. 'X-Xenarch-Tx-Hash'.
	 * @return string|null
	 */
	private static function read_header( $server_key, $header_name ) {
		if ( isset( $_SERVER[ $server_key ] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( is_array( $headers ) ) {
				$lower_name = strtolower( $header_name );
				foreach ( $headers as $name => $value ) {
					if ( strtolower( $name ) === $lower_name ) {
						return sanitize_text_field( $value );
					}
				}
			}
		}

		return null;
	}

	/**
	 * Verify a tx hash satisfies the named gate.
	 *
	 * @param string $gate_id Gate UUID, as supplied by the agent in the X-Xenarch-Gate-Id header.
	 * @param string $tx_hash Lower-case 0x-prefixed transaction hash.
	 * @return bool True if the platform confirms the payment.
	 */
	public static function verify( $gate_id, $tx_hash ) {
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

		// Platform returns {gate_id, status, tx_hash, amount_usd, verified_at}.
		// Treat status="paid" as the canonical success signal; keep
		// 'verified'/'valid' fallbacks so the plugin survives minor schema drift.
		$status   = isset( $result['status'] ) ? strtolower( (string) $result['status'] ) : '';
		$is_valid = 'paid' === $status
			|| ! empty( $result['verified'] )
			|| ! empty( $result['valid'] );
		set_transient( $cache_key, $is_valid ? 'valid' : 'invalid', self::CACHE_TTL );

		return $is_valid;
	}
}

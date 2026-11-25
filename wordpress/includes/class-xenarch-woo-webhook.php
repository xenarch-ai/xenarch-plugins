<?php
/**
 * XEN-681 — receiver for the connected pay link's ``payment.*`` webhooks.
 *
 * This is the ONLY code path that marks a WooCommerce order paid. In order:
 *
 *   1. HMAC check of ``X-Xenarch-Signature`` (t.body, ±300 s) with the
 *      link's webhook secret → 401 on failure.
 *   2. ``link_id`` must be the connected link and ``data.to`` its merchant
 *      wallet. XEN-678: ``data.network`` / ``data.token`` say where it was
 *      paid (USDC on Base, or USDC/USDT on a network the store turned on);
 *      amounts are 6-decimal units of that dollar coin either way.
 *   3. ``data.client_reference_id`` = ``wc:<order id>:<order key>`` → the
 *      order must exist, use this gateway and carry that key.
 *   4. Amount: ``value_micro_usdc`` (what actually moved on-chain) must be
 *      ≥ the order total in micro-USDC. The platform's own status is NOT
 *      used: on an open-amount link any in-bounds amount is "confirmed".
 *   5. Paid in full → payment_complete( tx ) (→ Processing) + note.
 *      Short → on-hold + note with both amounts.
 *
 * Idempotent: a tx hash already booked on any order is a no-op; a per-order
 * MySQL lock serialises concurrent retries of the same delivery. Anything
 * that isn't ours (no/foreign reference, other event) gets a 200 so the
 * platform doesn't retry it forever.
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenarch_Woo_Webhook {

	/**
	 * @param WP_REST_Request $request Incoming delivery.
	 * @return WP_REST_Response
	 */
	public static function handle( $request ) {
		$body = (string) $request->get_body();
		$sig  = (string) $request->get_header( 'x_xenarch_signature' );

		if ( ! Xenarch_Woo::verify_signature( $sig, $body, Xenarch_Woo::webhook_secret(), time() ) ) {
			return self::reply( 401, 'bad_signature' );
		}

		$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) ) {
			return self::reply( 400, 'bad_json' );
		}
		$event = isset( $payload['event_type'] ) ? (string) $payload['event_type'] : '';
		$data  = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();

		if ( ! in_array( $event, Xenarch_Woo::EVENTS, true ) ) {
			return self::reply( 200, 'ignored_event' );
		}
		if ( ! isset( $payload['link_id'] ) || (string) $payload['link_id'] !== Xenarch_Woo::link_id() ) {
			return self::reply( 200, 'ignored_link' );
		}

		$ref = Xenarch_Woo::parse_reference( isset( $data['client_reference_id'] ) ? $data['client_reference_id'] : null );
		if ( null === $ref ) {
			return self::reply( 200, 'ignored_reference' );
		}

		$network = isset( $data['network'] ) ? (string) $data['network'] : 'base';
		$token   = isset( $data['token'] ) ? strtoupper( (string) $data['token'] ) : 'USDC';
		if ( ! isset( Xenarch_Woo::NETWORKS[ $network ] ) || ! in_array( $token, Xenarch_Woo::NETWORKS[ $network ][1], true ) ) {
			// A network/coin this plugin doesn't know: never book it blind.
			return self::reply( 200, 'ignored_network' );
		}

		// XEN-678: each network writes hashes and addresses its own way. EVM:
		// 0x hex, compared lowercase. Tron: 64 hex without 0x, and a base58
		// T-address compared exactly (base58 is case-sensitive).
		$tx    = Xenarch_Woo::normalize_tx( $network, isset( $data['tx_hash'] ) ? $data['tx_hash'] : '' );
		$to    = isset( $data['to'] ) ? trim( (string) $data['to'] ) : '';
		$to    = 'tron' === $network ? $to : strtolower( $to );
		$value = isset( $data['value_micro_usdc'] ) ? $data['value_micro_usdc'] : null;
		if ( '' === $tx || ! ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) ) {
			return self::reply( 400, 'bad_payload' );
		}
		$value  = (int) $value;
		$pay_to = Xenarch_Woo::pay_to_for( $network );
		if ( '' === $pay_to || ! hash_equals( $pay_to, $to ) ) {
			// Signed by our secret yet paid elsewhere: never book it.
			return self::reply( 200, 'ignored_wrong_recipient' );
		}

		list( $order_id, $order_key ) = $ref;
		$lock = 'xenarch_woo_order_' . $order_id;
		if ( ! self::lock( $lock ) ) {
			return self::reply( 503, 'busy' ); // Platform retries.
		}
		try {
			return self::apply( $order_id, $order_key, $tx, $value, $network, $token );
		} finally {
			self::unlock( $lock );
		}
	}

	/**
	 * Book one verified transfer onto its order.
	 */
	private static function apply( $order_id, $order_key, $tx, $value, $network = 'base', $token = 'USDC' ) {
		$where = sprintf( '%s on %s', $token, Xenarch_Woo::network_name( $network ) );
		$order = wc_get_order( $order_id );
		if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
			return self::reply( 200, 'ignored_unknown_order' );
		}
		if ( Xenarch_Woo::GATEWAY_ID !== $order->get_payment_method() ) {
			return self::reply( 200, 'ignored_other_gateway' );
		}

		// A tx pays at most one order, once.
		$booked = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => Xenarch_Woo::META_TX,
						'value' => $tx,
					),
				),
			)
		);
		if ( ! empty( $booked ) ) {
			return self::reply( 200, 'duplicate' );
		}

		$order_micro = Xenarch_Woo::to_micro( $order->get_total() );
		if ( null === $order_micro || 'USD' !== $order->get_currency() ) {
			// USDC settles dollars; never auto-mark a non-USD order paid.
			$order->add_meta_data( Xenarch_Woo::META_TX, $tx, false );
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: 1: tx hash */
					__( 'Xenarch: a dollar-coin payment arrived (tx %1$s) but this order is not in USD, so it was not marked paid. Check it by hand.', 'xenarch' ),
					$tx
				)
			);
			return self::reply( 200, 'ignored_currency' );
		}
		$paid_str = Xenarch_Woo::micro_to_decimal( $value );
		$due_str  = Xenarch_Woo::micro_to_decimal( $order_micro );
		$tx_link  = Xenarch_Woo::explorer_url( $network, $tx );

		$order->add_meta_data( Xenarch_Woo::META_TX, $tx, false );

		if ( $order->is_paid() ) {
			// Already paid by an earlier transfer: record the extra one.
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: 1: amount, 2: tx hash, 3: explorer link */
					__( 'Xenarch: another payment of %1$s arrived after this order was already paid (tx %2$s, %3$s). Refund it from your wallet if needed.', 'xenarch' ),
					$paid_str . ' ' . $where,
					$tx,
					$tx_link
				)
			);
			return self::reply( 200, 'extra_payment' );
		}

		// XEN-678: what the order-received receipt shows.
		$order->update_meta_data( '_xenarch_network', $network );
		$order->update_meta_data( '_xenarch_token', $token );
		$order->update_meta_data( '_xenarch_paid_micro', (string) $value );
		$order->update_meta_data( '_xenarch_paid_at', (string) time() );

		if ( $value >= $order_micro ) {
			// payment_complete() saves the tx meta together with the status,
			// so a failure here leaves nothing behind and the retry books it.
			$payable = apply_filters(
				'woocommerce_valid_order_statuses_for_payment_complete',
				array( 'on-hold', 'pending', 'failed', 'cancelled' ),
				$order
			);
			if ( ! $order->has_status( $payable ) ) {
				// e.g. refunded: record the money, leave the status to a human.
				$order->save();
				$order->add_order_note(
					sprintf(
						/* translators: 1: amount, 2: order status, 3: tx hash */
						__( 'Xenarch: received %1$s, but the order is %2$s so it was not marked paid. Tx %3$s', 'xenarch' ),
						$paid_str . ' ' . $where,
						$order->get_status(),
						$tx
					)
				);
				return self::reply( 200, 'not_payable_status' );
			}
			if ( ! $order->payment_complete( $tx ) ) {
				return self::reply( 500, 'payment_complete_failed' ); // Platform retries.
			}
			$order->add_order_note(
				sprintf(
					/* translators: 1: amount paid, 2: amount due, 3: tx hash, 4: explorer link */
					__( 'Xenarch: paid %1$s (order total %2$s). Tx %3$s — %4$s', 'xenarch' ),
					$paid_str . ' ' . $where,
					$due_str,
					$tx,
					$tx_link
				)
			);
			return self::reply( 200, 'paid' );
		}

		$note = sprintf(
			/* translators: 1: amount paid, 2: amount due, 3: tx hash, 4: explorer link */
			__( 'Xenarch: underpaid. Received %1$s, order total is %2$s. Less than the order total arrived (a wallet or exchange may have deducted a fee): ask for the rest or refund. Tx %3$s — %4$s', 'xenarch' ),
			$paid_str . ' ' . $where,
			$due_str,
			$tx,
			$tx_link
		);
		if ( $order->has_status( array( 'pending', 'failed', 'cancelled' ) ) ) {
			$order->update_status( 'on-hold', $note ); // Saves the tx meta too.
		} else {
			$order->save();
			$order->add_order_note( $note );
		}
		return self::reply( 200, 'underpaid' );
	}

	private static function lock( $name ) {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private static function unlock( $name ) {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private static function reply( $status, $result ) {
		return new WP_REST_Response( array( 'result' => $result ), $status );
	}
}

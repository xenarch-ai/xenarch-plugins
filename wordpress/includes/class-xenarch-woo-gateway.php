<?php
/**
 * XEN-681 — the "Crypto: USDC" WooCommerce payment gateway.
 *
 * Place order → the order stays pending and the buyer goes to the Xenarch
 * hosted pay page for the order total. The order is marked paid only by
 * the signed webhook (Xenarch_Woo_Webhook), never by the redirect back.
 *
 * Settings live in the plugin's own admin (Settings → /xenarch → Checkout,
 * design-system controls); the WooCommerce settings screen for this gateway
 * just points there. ``enabled`` / ``title`` stay in WooCommerce's own
 * gateway option so the WooCommerce → Payments toggle keeps working.
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenarch_Woo_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = Xenarch_Woo::GATEWAY_ID;
		$this->method_title       = __( 'Xenarch: USDC and USDT', 'xenarch' );
		$this->method_description = __( 'Buyers pay in USDC or USDT from their own wallet or an exchange, straight to your wallet. The order moves to Processing when the payment lands, or On hold when it arrives short.', 'xenarch' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->order_button_text  = __( 'Pay with crypto', 'xenarch' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = Xenarch_Woo::title();
		$this->description = Xenarch_Woo::buyer_description();
		$this->enabled     = Xenarch_Woo::is_enabled() ? 'yes' : 'no';

		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou' ) );
	}

	/**
	 * Only the fields WooCommerce itself reads (Payments list toggle).
	 * Edited in the plugin admin, not here.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable', 'xenarch' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'title'   => array(
				'title'   => __( 'Label', 'xenarch' ),
				'type'    => 'text',
				'default' => Xenarch_Woo::DEFAULT_TITLE,
			),
		);
	}

	/** WooCommerce → Payments → Manage: point to the plugin's own settings. */
	public function admin_options() {
		$url = admin_url( 'options-general.php?page=xenarch&tab=checkout' );
		echo '<h2>' . esc_html( $this->method_title ) . '</h2>';
		echo '<p>' . esc_html( $this->method_description ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open Xenarch checkout settings', 'xenarch' ) . '</a></p>';
	}

	/** Nothing to save on the WooCommerce screen; settings save in the plugin admin. */
	public function process_admin_options() {
		return true;
	}

	public function is_available() {
		return Xenarch_Woo::is_enabled()
			&& Xenarch_Woo::is_connected()
			&& Xenarch_Woo::currency_ok()
			&& parent::is_available();
	}

	/**
	 * Send the buyer to the hosted pay page. The order stays pending.
	 *
	 * @param int $order_id Order id.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'xenarch' ), 'error' );
			return array( 'result' => 'failure' );
		}
		if ( null === Xenarch_Woo::to_micro( $order->get_total() ) || (float) $order->get_total() <= 0 ) {
			wc_add_notice( __( 'This order total cannot be paid in USDC.', 'xenarch' ), 'error' );
			return array( 'result' => 'failure' );
		}
		$order->update_meta_data( '_xenarch_link_id', Xenarch_Woo::link_id() );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => Xenarch_Woo::pay_url( $order ),
		);
	}

	/**
	 * Order-received page: while the webhook hasn't landed yet, tell the
	 * buyer and refresh a few times so the status flips on its own.
	 *
	 * @param int $order_id Order id.
	 */
	/**
	 * XEN-678: the payment receipt on the order-received page, as the demo's
	 * "Payment" card: amount, network, transaction, time.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $tx    Transaction hash.
	 */
	private static function receipt( $order, $tx ) {
		$network = (string) $order->get_meta( '_xenarch_network' );
		$network = '' !== $network ? $network : 'base';
		$token   = (string) $order->get_meta( '_xenarch_token' );
		$token   = '' !== $token ? $token : 'USDC';
		$micro   = (string) $order->get_meta( '_xenarch_paid_micro' );
		$at      = (int) $order->get_meta( '_xenarch_paid_at' );
		$amount  = ctype_digit( $micro ) ? preg_replace( '/(\.\d{2}\d*?)0+$/', '$1', Xenarch_Woo::micro_to_decimal( (int) $micro ) ) : '';
		$rows    = array();
		if ( '' !== $amount ) {
			$rows[] = array( __( 'Amount', 'xenarch' ), esc_html( $amount . ' ' . $token ) );
		}
		$rows[] = array( __( 'Network', 'xenarch' ), esc_html( Xenarch_Woo::network_name( $network ) ) );
		$rows[] = array(
			__( 'Transaction', 'xenarch' ),
			'<a href="' . esc_url( Xenarch_Woo::explorer_url( $network, $tx ) ) . '" target="_blank" rel="noopener noreferrer"><code>' . esc_html( substr( $tx, 0, 6 ) . '…' . substr( $tx, -4 ) ) . '</code></a>',
		);
		if ( $at > 0 ) {
			$rows[] = array( __( 'Paid', 'xenarch' ), esc_html( wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $at ) ) );
		}
		echo '<section class="xenarch-woo-paid"><h2>' . esc_html__( 'Payment', 'xenarch' ) . '</h2><table class="woocommerce-table shop_table"><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . $r[1] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		}
		echo '</tbody></table><p class="xenarch-woo-by">' . esc_html__( 'Paid from your wallet or exchange, straight to the store.', 'xenarch' ) . ' <a href="https://xenarch.com" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Payments by /xenarch', 'xenarch' ) . '</a></p></section>';
	}

	public function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( $order->is_paid() ) {
			$tx = $order->get_transaction_id();
			if ( $tx ) {
				self::receipt( $order, $tx );
			}
			return;
		}
		if ( $order->has_status( 'on-hold' ) ) {
			echo '<p class="xenarch-woo-onhold">' . esc_html__( 'We received a payment that is less than the order total. The store has your order on hold and will email you about the missing amount.', 'xenarch' ) . '</p>';
			return;
		}
		if ( ! $order->has_status( 'pending' ) ) {
			return;
		}
		echo '<p class="xenarch-woo-waiting">' . esc_html__( 'Waiting for your payment to confirm. This page refreshes on its own.', 'xenarch' ) . '</p>';
		// Up to ~2 minutes of 6-second refreshes per order, then stop.
		$key = 'xenarch_woo_wait_' . (int) $order_id;
		echo '<script>(function(){try{var k=' . wp_json_encode( $key ) . ',n=parseInt(sessionStorage.getItem(k)||"0",10);if(n<20){sessionStorage.setItem(k,String(n+1));setTimeout(function(){location.reload();},6000);}}catch(e){}})();</script>';
	}
}

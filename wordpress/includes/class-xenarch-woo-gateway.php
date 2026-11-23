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
		$this->method_title       = __( 'Xenarch: USDC on Base', 'xenarch' );
		$this->method_description = __( 'Buyers pay in USDC on Base from their own wallet, straight to your wallet. The order moves to Processing when the payment lands.', 'xenarch' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->order_button_text  = __( 'Pay with USDC', 'xenarch' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = Xenarch_Woo::title();
		$this->description = __( 'Pay in USDC on Base from your own wallet. You will be taken to a secure Xenarch page to pay.', 'xenarch' );
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
	public function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( $order->is_paid() ) {
			$tx = $order->get_transaction_id();
			if ( $tx ) {
				echo '<p class="xenarch-woo-paid">' . esc_html__( 'Paid in USDC on Base.', 'xenarch' ) . ' <a href="' . esc_url( 'https://basescan.org/tx/' . $tx ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View transaction', 'xenarch' ) . '</a></p>';
			}
			return;
		}
		if ( $order->has_status( 'on-hold' ) ) {
			echo '<p class="xenarch-woo-onhold">' . esc_html__( 'We received a payment that is less than the order total. The store will contact you.', 'xenarch' ) . '</p>';
			return;
		}
		if ( ! $order->has_status( 'pending' ) ) {
			return;
		}
		echo '<p class="xenarch-woo-waiting">' . esc_html__( 'Waiting for your USDC payment to confirm on Base. This page refreshes on its own.', 'xenarch' ) . '</p>';
		// Up to ~2 minutes of 6-second refreshes per order, then stop.
		$key = 'xenarch_woo_wait_' . (int) $order_id;
		echo '<script>(function(){try{var k=' . wp_json_encode( $key ) . ',n=parseInt(sessionStorage.getItem(k)||"0",10);if(n<20){sessionStorage.setItem(k,String(n+1));setTimeout(function(){location.reload();},6000);}}catch(e){}})();</script>';
	}
}

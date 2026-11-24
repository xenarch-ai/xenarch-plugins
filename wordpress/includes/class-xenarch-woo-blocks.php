<?php
/**
 * XEN-681 — registers the gateway with WooCommerce Cart & Checkout Blocks
 * (the default checkout on current WooCommerce). The classic shortcode
 * checkout uses Xenarch_Woo_Gateway directly.
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Xenarch_Woo_Blocks extends AbstractPaymentMethodType {

	protected $name = 'xenarch';

	public function initialize() {
		$this->settings = Xenarch_Woo::gateway_settings();
	}

	public function is_active() {
		return Xenarch_Woo::is_enabled() && Xenarch_Woo::is_connected() && Xenarch_Woo::currency_ok();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'xenarch-woo-blocks',
			XENARCH_PLUGIN_URL . 'assets/js/woo-blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			XENARCH_VERSION,
			true
		);
		return array( 'xenarch-woo-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => Xenarch_Woo::title(),
			'description' => Xenarch_Woo::buyer_description(),
			'button'      => __( 'Pay with crypto', 'xenarch' ),
			'supports'    => array( 'products' ),
		);
	}
}

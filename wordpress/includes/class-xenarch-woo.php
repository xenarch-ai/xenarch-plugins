<?php
/**
 * XEN-681 — WooCommerce checkout: pay an order in USDC on Base.
 *
 * How one order becomes one payment
 * ---------------------------------
 * Pay links are signed by the merchant's wallet, so the plugin can't mint a
 * link per order. The merchant signs ONE reusable open-amount checkout link
 * in the Xenarch dashboard and connects it here with a merchant key
 * (``xm_live_*``). Each order is a payment intent on that link:
 *
 *   pay.xenarch.com/l/<link>?amount=<total>&client_reference_id=wc:<id>:<key>
 *                           &return_url=<order-received URL>
 *
 * The hosted page fixes ``client_reference_id`` on the intent (XEN-668), the
 * platform's watcher books the buyer's transfer (XEN-679) and fires a signed
 * ``payment.*`` webhook carrying ``client_reference_id``, ``tx_hash``, ``to``
 * and ``value_micro_usdc``. Xenarch_Woo_Webhook is the ONLY path that marks
 * an order paid, and only when the on-chain value covers the order total.
 * Everything in the URL is buyer-editable and never trusted.
 *
 * State (all plugin-local, none autoloaded except the WC gateway settings):
 *   woocommerce_xenarch_settings  enabled / title  (WC's own gateway option,
 *                                 so the WooCommerce → Payments toggle works)
 *   xenarch_woo_link_id           connected pay link id
 *   xenarch_woo_pay_to            the link's merchant wallet (lowercase)
 *   xenarch_woo_merchant_key      xm_live_* key       (secret, never echoed)
 *   xenarch_woo_webhook_secret    link webhook secret (secret, never echoed)
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Xenarch_Woo {

	const GATEWAY_ID       = 'xenarch';
	const OPT_LINK_ID      = 'xenarch_woo_link_id';
	const OPT_PAY_TO       = 'xenarch_woo_pay_to';
	const OPT_MERCHANT_KEY = 'xenarch_woo_merchant_key';
	const OPT_SECRET       = 'xenarch_woo_webhook_secret';
	const OPT_GATEWAY      = 'woocommerce_xenarch_settings';
	const META_TX          = '_xenarch_tx_hash';
	const REF_PREFIX       = 'wc';
	const DEFAULT_TITLE    = 'Crypto: USDC';

	/** Webhook events the receiver handles. */
	const EVENTS = array( 'payment.confirmed', 'payment.underpaid', 'payment.overpaid' );

	/**
	 * Hook everything. Called on plugins_loaded; a no-op without WooCommerce
	 * except the admin REST status route, which reports "not installed".
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}
		require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-woo-gateway.php';
		require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-woo-webhook.php';

		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Declare HPOS + Cart/Checkout Blocks compatibility. Must run on
	 * before_woocommerce_init, which fires before plugins_loaded callbacks
	 * at default priority, so it's hooked from the main plugin file.
	 */
	public static function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			$main = XENARCH_PLUGIN_DIR . 'xenarch.php';
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $main, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $main, true );
		}
	}

	public static function add_gateway( $gateways ) {
		$gateways[] = 'Xenarch_Woo_Gateway';
		return $gateways;
	}

	public static function register_blocks( $registry ) {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once XENARCH_PLUGIN_DIR . 'includes/class-xenarch-woo-blocks.php';
		$registry->register( new Xenarch_Woo_Blocks() );
	}

	// ------------------------------------------------------------------
	// Config
	// ------------------------------------------------------------------

	public static function woo_active() {
		return class_exists( 'WooCommerce' );
	}

	/** True when a link, its wallet, the key and the webhook secret are all set. */
	public static function is_connected() {
		return '' !== self::link_id()
			&& '' !== self::pay_to()
			&& '' !== (string) get_option( self::OPT_MERCHANT_KEY, '' )
			&& '' !== self::webhook_secret();
	}

	public static function link_id() {
		return (string) get_option( self::OPT_LINK_ID, '' );
	}

	public static function pay_to() {
		return strtolower( (string) get_option( self::OPT_PAY_TO, '' ) );
	}

	public static function webhook_secret() {
		return (string) get_option( self::OPT_SECRET, '' );
	}

	public static function gateway_settings() {
		$s = get_option( self::OPT_GATEWAY, array() );
		return is_array( $s ) ? $s : array();
	}

	public static function is_enabled() {
		$s = self::gateway_settings();
		return isset( $s['enabled'] ) && 'yes' === $s['enabled'];
	}

	public static function title() {
		$s     = self::gateway_settings();
		$title = isset( $s['title'] ) ? trim( (string) $s['title'] ) : '';
		return '' !== $title ? $title : self::DEFAULT_TITLE;
	}

	/** USDC is a dollar token: the option is only offered in a USD store. */
	public static function currency_ok() {
		return function_exists( 'get_woocommerce_currency' ) && 'USD' === get_woocommerce_currency();
	}

	public static function pay_base_url() {
		return rtrim( (string) apply_filters( 'xenarch_pay_base', 'https://pay.xenarch.com' ), '/' );
	}

	public static function dashboard_url() {
		return rtrim( (string) apply_filters( 'xenarch_dashboard_base', 'https://dash.xenarch.dev' ), '/' );
	}

	public static function webhook_url() {
		return rest_url( Xenarch_Rest::NAMESPACE . '/woo/webhook' );
	}

	// ------------------------------------------------------------------
	// Pure helpers (no WordPress calls — unit-testable)
	// ------------------------------------------------------------------

	/**
	 * Decimal amount string → integer micro-USDC, exact (no floats).
	 * Rounds UP past 6 decimals so a converted order total is never less
	 * than the order. Returns null for anything that isn't a plain
	 * non-negative decimal.
	 *
	 * @param string|int|float $amount e.g. "0.01", "12", "3.1234567".
	 * @return int|null
	 */
	public static function to_micro( $amount ) {
		$s = trim( (string) $amount );
		if ( ! preg_match( '/^(\d{1,12})(?:\.(\d+))?$/', $s, $m ) ) {
			return null;
		}
		$whole = (int) $m[1];
		$frac  = isset( $m[2] ) ? $m[2] : '';
		$head  = substr( str_pad( $frac, 6, '0' ), 0, 6 );
		$tail  = (string) substr( $frac, 6 );
		$micro = $whole * 1000000 + (int) $head;
		if ( '' !== $tail && '' !== trim( $tail, '0' ) ) {
			++$micro;
		}
		return $micro;
	}

	/** Integer micro-USDC → "0.010000"-style decimal string (6 dp). */
	public static function micro_to_decimal( $micro ) {
		$micro = (int) $micro;
		return intdiv( $micro, 1000000 ) . '.' . str_pad( (string) ( $micro % 1000000 ), 6, '0', STR_PAD_LEFT );
	}

	/** The reference the hosted page fixes on the intent for an order. */
	public static function build_reference( $order_id, $order_key ) {
		return self::REF_PREFIX . ':' . (int) $order_id . ':' . $order_key;
	}

	/**
	 * Parse ``wc:<id>:<key>``. Returns ``array( id, key )`` or null.
	 *
	 * @param mixed $ref client_reference_id from the webhook.
	 * @return array|null
	 */
	public static function parse_reference( $ref ) {
		if ( ! is_string( $ref ) ) {
			return null;
		}
		if ( ! preg_match( '/^' . self::REF_PREFIX . ':(\d{1,20}):(wc_order_[A-Za-z0-9]{1,64})$/', $ref, $m ) ) {
			return null;
		}
		return array( (int) $m[1], $m[2] );
	}

	/**
	 * Verify ``X-Xenarch-Signature: t=<unix>,v1=<hex>`` where
	 * v1 = HMAC_SHA256(secret, "<t>.<raw body>"). Rejects a timestamp
	 * outside ±$tolerance seconds (replay window).
	 *
	 * @param string $header    Raw header value.
	 * @param string $body      Raw request body.
	 * @param string $secret    Link webhook secret.
	 * @param int    $now       Current unix time.
	 * @param int    $tolerance Seconds.
	 * @return bool
	 */
	public static function verify_signature( $header, $body, $secret, $now, $tolerance = 300 ) {
		if ( '' === (string) $secret || ! is_string( $header ) || '' === $header ) {
			return false;
		}
		$t   = null;
		$v1s = array();
		foreach ( explode( ',', $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] && ctype_digit( $kv[1] ) ) {
				$t = (int) $kv[1];
			} elseif ( 'v1' === $kv[0] && preg_match( '/^[0-9a-f]{64}$/', $kv[1] ) ) {
				$v1s[] = $kv[1];
			}
		}
		if ( null === $t || empty( $v1s ) || abs( (int) $now - $t ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $body, $secret );
		foreach ( $v1s as $v1 ) {
			if ( hash_equals( $expected, $v1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Accept a link id or a pay.xenarch.com/l/<id> URL. Returns the id or ''.
	 *
	 * @param string $input Raw merchant input.
	 * @return string
	 */
	public static function parse_link_id( $input ) {
		$input = trim( (string) $input );
		if ( preg_match( '#/l/([A-Za-z0-9_-]{6,64})(?:[/?\#].*)?$#', $input, $m ) ) {
			return $m[1];
		}
		return preg_match( '/^[A-Za-z0-9_-]{6,64}$/', $input ) ? $input : '';
	}

	/** ``params.<key>.value`` when the param is lit, else null. */
	public static function read_lit( $params, $key ) {
		if ( ! is_array( $params ) || ! isset( $params[ $key ] ) || ! is_array( $params[ $key ] ) ) {
			return null;
		}
		$entry = $params[ $key ];
		if ( ! isset( $entry['state'] ) || 'lit' !== $entry['state'] || ! array_key_exists( 'value', $entry ) ) {
			return null;
		}
		return $entry['value'];
	}

	/**
	 * Why a link can't take WooCommerce orders, or '' when it can.
	 *
	 * @param array $link Owner detail from GET /v1/links/{id}.
	 * @return string
	 */
	public static function link_problem( $link ) {
		$params = isset( $link['params'] ) ? $link['params'] : array();
		if ( ! isset( $link['status'] ) || 'active' !== $link['status'] ) {
			return 'This link is not active.';
		}
		if ( 'open' !== self::read_lit( $params, 'amount' ) ) {
			return 'Use a link with an open amount (the buyer\'s order total is filled in per order).';
		}
		if ( true === self::read_lit( $params, 'single_use' ) ) {
			return 'Use a reusable link, not a single-use one.';
		}
		if ( 'USDC' !== strtoupper( (string) self::read_lit( $params, 'currency' ) ) ) {
			return 'The link must take USDC.';
		}
		if ( 'base' !== strtolower( (string) self::read_lit( $params, 'network' ) ) ) {
			return 'The link must be on Base.';
		}
		$kind = (string) self::read_lit( $params, 'kind' );
		if ( in_array( $kind, array( 'subscription', 'api_metering' ), true ) ) {
			return 'Use a one-time checkout link, not a subscription.';
		}
		$to = self::read_lit( $params, 'to' );
		if ( ! is_string( $to ) || ! preg_match( '/^0x[0-9a-fA-F]{40}$/', $to ) ) {
			return 'The link has no valid payout wallet.';
		}
		return '';
	}

	// ------------------------------------------------------------------
	// Checkout redirect
	// ------------------------------------------------------------------

	/**
	 * The hosted pay page URL for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function pay_url( $order ) {
		$micro = self::to_micro( $order->get_total() );
		$args  = array(
			'amount'              => rtrim( rtrim( self::micro_to_decimal( (int) $micro ), '0' ), '.' ),
			'client_reference_id' => self::build_reference( $order->get_id(), $order->get_order_key() ),
			'return_url'          => $order->get_checkout_order_received_url(),
		);
		return self::pay_base_url() . '/l/' . rawurlencode( self::link_id() ) . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	// ------------------------------------------------------------------
	// REST: admin settings + public webhook receiver
	// ------------------------------------------------------------------

	public static function register_routes() {
		$admin = array( __CLASS__, 'check_admin' );
		register_rest_route(
			Xenarch_Rest::NAMESPACE,
			'/woo',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_get' ), 'permission_callback' => $admin ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_save' ), 'permission_callback' => $admin ),
			)
		);
		register_rest_route(
			Xenarch_Rest::NAMESPACE,
			'/woo/connect',
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_connect' ), 'permission_callback' => $admin )
		);
		register_rest_route(
			Xenarch_Rest::NAMESPACE,
			'/woo/disconnect',
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_disconnect' ), 'permission_callback' => $admin )
		);
		if ( class_exists( 'Xenarch_Woo_Webhook' ) ) {
			// Public by design: authenticated by the HMAC signature, not a
			// WP session (the platform can't hold one).
			register_rest_route(
				Xenarch_Rest::NAMESPACE,
				'/woo/webhook',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'Xenarch_Woo_Webhook', 'handle' ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * Admins only (same as the plugin's admin page). Deliberately NOT
	 * manage_woocommerce: whoever can connect a link decides where store
	 * revenue goes, so a shop manager must not be able to repoint it.
	 */
	public static function check_admin() {
		return current_user_can( 'manage_options' );
	}

	/** Plugin-local status for the admin Checkout tab. Never includes secrets. */
	public static function snapshot() {
		$key  = (string) get_option( self::OPT_MERCHANT_KEY, '' );
		$link = self::link_id();
		return array(
			'woo_active'    => self::woo_active(),
			'currency'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'currency_ok'   => self::currency_ok(),
			'connected'     => self::is_connected(),
			'enabled'       => self::is_enabled(),
			'title'         => self::title(),
			'link_id'       => $link,
			'link_url'      => '' !== $link ? self::pay_base_url() . '/l/' . $link : '',
			'pay_to'        => self::pay_to(),
			'key_preview'   => '' !== $key ? substr( $key, 0, 8 ) . '…' . substr( $key, -4 ) : '',
			'webhook_url'   => self::webhook_url(),
			'dashboard_url' => self::dashboard_url(),
		);
	}

	public static function rest_get() {
		return new WP_REST_Response( self::snapshot(), 200 );
	}

	/** Save enabled + title into WooCommerce's own gateway option. */
	public static function rest_save( $request ) {
		$body     = $request->get_json_params();
		$settings = self::gateway_settings();
		if ( isset( $body['enabled'] ) ) {
			if ( $body['enabled'] && ! self::is_connected() ) {
				return self::error( 'not_connected', 'Connect a pay link before turning the option on.', 400 );
			}
			$settings['enabled'] = $body['enabled'] ? 'yes' : 'no';
		}
		if ( isset( $body['title'] ) ) {
			$title = sanitize_text_field( (string) $body['title'] );
			if ( '' === $title || strlen( $title ) > 80 ) {
				return self::error( 'bad_title', 'The label must be 1 to 80 characters.', 400 );
			}
			$settings['title'] = $title;
		}
		update_option( self::OPT_GATEWAY, $settings );
		return new WP_REST_Response( self::snapshot(), 200 );
	}

	/**
	 * Connect: check the key + link, point the link's webhook here, rotate
	 * and keep its signing secret. Nothing is stored unless every step works.
	 */
	public static function rest_connect( $request ) {
		$body    = $request->get_json_params();
		$key     = isset( $body['merchant_key'] ) ? trim( (string) $body['merchant_key'] ) : '';
		$link_id = self::parse_link_id( isset( $body['link'] ) ? $body['link'] : '' );

		if ( '' === $key ) {
			// Reconnecting a different link with the key already on file.
			$key = (string) get_option( self::OPT_MERCHANT_KEY, '' );
		}
		if ( ! preg_match( '/^xm_[a-z]+_[A-Za-z0-9_-]{8,200}$/', $key ) ) {
			return self::error( 'bad_key', 'Paste a Xenarch merchant key (it starts with xm_live_).', 400 );
		}
		if ( '' === $link_id ) {
			return self::error( 'bad_link', 'Paste the pay link URL (pay.xenarch.com/l/…) or its id.', 400 );
		}

		$api  = new Xenarch_Api();
		$link = $api->merchant_get_link( $key, $link_id );
		if ( is_wp_error( $link ) ) {
			$status = (int) $link->get_error_data( 'status' );
			if ( 401 === $status ) {
				return self::error( 'bad_key', 'Xenarch rejected this merchant key.', 400 );
			}
			if ( 404 === $status ) {
				return self::error( 'bad_link', 'No link with that id on this Xenarch account.', 400 );
			}
			return self::error( 'api', 'Could not reach Xenarch: ' . $link->get_error_message(), 502 );
		}
		$problem = self::link_problem( $link );
		if ( '' !== $problem ) {
			return self::error( 'bad_link', $problem, 400 );
		}

		$hook = $api->merchant_put_link_webhook( $key, $link_id, self::webhook_url(), self::EVENTS, true );
		if ( is_wp_error( $hook ) ) {
			return self::error( 'webhook', 'Xenarch would not accept this site\'s webhook URL: ' . $hook->get_error_message(), 502 );
		}
		$rot = $api->merchant_rotate_link_webhook_secret( $key, $link_id );
		if ( is_wp_error( $rot ) || empty( $rot['webhook_secret'] ) || ! is_string( $rot['webhook_secret'] ) ) {
			return self::error( 'webhook', 'Could not set up the webhook signing secret. Try again.', 502 );
		}

		self::set_private_option( self::OPT_MERCHANT_KEY, $key );
		self::set_private_option( self::OPT_SECRET, $rot['webhook_secret'] );
		self::set_private_option( self::OPT_LINK_ID, $link_id );
		self::set_private_option( self::OPT_PAY_TO, strtolower( self::read_lit( $link['params'], 'to' ) ) );

		return new WP_REST_Response( self::snapshot(), 200 );
	}

	/**
	 * Disconnect: turn the option off, switch the link's webhook off
	 * (best effort) and forget the key and secret.
	 */
	public static function rest_disconnect() {
		$key  = (string) get_option( self::OPT_MERCHANT_KEY, '' );
		$link = self::link_id();
		if ( '' !== $key && '' !== $link ) {
			( new Xenarch_Api() )->merchant_put_link_webhook( $key, $link, self::webhook_url(), self::EVENTS, false );
		}
		$settings            = self::gateway_settings();
		$settings['enabled'] = 'no';
		update_option( self::OPT_GATEWAY, $settings );
		foreach ( array( self::OPT_MERCHANT_KEY, self::OPT_SECRET, self::OPT_LINK_ID, self::OPT_PAY_TO ) as $opt ) {
			delete_option( $opt );
		}
		return new WP_REST_Response( self::snapshot(), 200 );
	}

	/** Store without autoload so secrets aren't loaded on every request. */
	private static function set_private_option( $name, $value ) {
		if ( false === get_option( $name, false ) ) {
			add_option( $name, $value, '', false );
		} else {
			update_option( $name, $value, false );
		}
	}

	private static function error( $code, $message, $status ) {
		return new WP_REST_Response( array( 'error' => $code, 'detail' => $message ), $status );
	}
}

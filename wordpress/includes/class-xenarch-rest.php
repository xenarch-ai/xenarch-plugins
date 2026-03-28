<?php
/**
 * Xenarch WP REST API endpoints.
 *
 * Provides a REST bridge between the React admin panel and
 * both wp_options (settings) and the Xenarch platform API
 * (stats, transactions).
 *
 * @package Xenarch
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Rest
 */
class Xenarch_Rest {

	/**
	 * API client instance.
	 *
	 * @var Xenarch_Api
	 */
	private $api;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'xenarch/v1';

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		$this->api = new Xenarch_Api();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		// Registration proxy.
		register_rest_route(
			$this->namespace,
			'/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_register' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Add site proxy.
		register_rest_route(
			$this->namespace,
			'/add-site',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_add_site' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Pricing rules.
		register_rest_route(
			$this->namespace,
			'/pricing-rules',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_pricing_rules' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_pricing_rules' ),
					'permission_callback' => array( $this, 'check_admin' ),
				),
			)
		);

		// Stats proxy (platform).
		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Transactions proxy (platform).
		register_rest_route(
			$this->namespace,
			'/transactions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_transactions' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Wallet balance (xenarch wallets only).
		register_rest_route(
			$this->namespace,
			'/balance',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_balance' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Category breakdown.
		register_rest_route(
			$this->namespace,
			'/category-breakdown',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_category_breakdown' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);

		// Withdraw (xenarch wallets only).
		register_rest_route(
			$this->namespace,
			'/withdraw',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_withdraw' ),
				'permission_callback' => array( $this, 'check_admin' ),
			)
		);
	}

	/**
	 * Permission check: current user must have manage_options.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'xenarch' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	// ------------------------------------------------------------------
	// Settings
	// ------------------------------------------------------------------

	/**
	 * GET /settings — read all Xenarch settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return new WP_REST_Response( $this->get_all_settings(), 200 );
	}

	/**
	 * POST /settings — update settings and sync to platform.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_settings( $request ) {
		$params = $request->get_json_params();

		$allowed = array(
			'xenarch_default_price',
			'xenarch_payout_wallet',
			'xenarch_gate_unknown_traffic',
			'xenarch_wallet_type',
		);

		foreach ( $allowed as $key ) {
			if ( isset( $params[ $key ] ) ) {
				update_option( $key, sanitize_text_field( $params[ $key ] ) );
			}
		}

		// Sync pricing to platform.
		$site_id = get_option( 'xenarch_site_id', '' );
		if ( ! empty( $site_id ) && isset( $params['xenarch_default_price'] ) ) {
			$rules      = json_decode( get_option( 'xenarch_pricing_rules', '[]' ), true );
			$api_rules  = array();

			if ( is_array( $rules ) ) {
				foreach ( $rules as $rule ) {
					if ( ! empty( $rule['path_contains'] ) && isset( $rule['price_usd'] ) ) {
						$api_rules[] = array(
							'path'      => '*' . $rule['path_contains'] . '*',
							'price_usd' => (float) $rule['price_usd'],
						);
					}
				}
			}

			$this->api->update_pricing(
				$site_id,
				(float) get_option( 'xenarch_default_price', '0.003' ),
				$api_rules
			);
		}

		// Sync payout wallet to platform.
		if ( isset( $params['xenarch_payout_wallet'] ) && ! empty( $params['xenarch_payout_wallet'] ) ) {
			$this->api->update_payout( $params['xenarch_payout_wallet'] );
		}

		return new WP_REST_Response( $this->get_all_settings(), 200 );
	}

	// ------------------------------------------------------------------
	// Registration / Site setup
	// ------------------------------------------------------------------

	/**
	 * POST /register — create publisher account.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_register( $request ) {
		$params   = $request->get_json_params();
		$email    = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
		$password = isset( $params['password'] ) ? $params['password'] : '';

		if ( empty( $email ) || empty( $password ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Email and password are required.' ),
				400
			);
		}

		$result = $this->api->register( $email, $password );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				400
			);
		}

		if ( ! empty( $result['api_key'] ) ) {
			update_option( 'xenarch_api_key', sanitize_text_field( $result['api_key'] ) );
			update_option( 'xenarch_email', $email );
		}

		return new WP_REST_Response( $this->get_all_settings(), 200 );
	}

	/**
	 * POST /add-site — register site domain.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_add_site( $request ) {
		$site_url = get_site_url();
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );

		if ( empty( $domain ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Could not detect site domain.' ),
				400
			);
		}

		$result = $this->api->add_site( $domain );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				400
			);
		}

		if ( ! empty( $result['id'] ) ) {
			update_option( 'xenarch_site_id', sanitize_text_field( $result['id'] ) );
		}
		if ( ! empty( $result['site_token'] ) ) {
			update_option( 'xenarch_site_token', sanitize_text_field( $result['site_token'] ) );
		}

		return new WP_REST_Response( $this->get_all_settings(), 200 );
	}

	// ------------------------------------------------------------------
	// Pricing rules
	// ------------------------------------------------------------------

	/**
	 * GET /pricing-rules — read pricing rules from wp_options.
	 *
	 * @return WP_REST_Response
	 */
	public function get_pricing_rules() {
		$rules = json_decode( get_option( 'xenarch_pricing_rules', '[]' ), true );
		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		return new WP_REST_Response( array( 'rules' => $rules ), 200 );
	}

	/**
	 * PUT /pricing-rules — save rules and sync to platform.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_pricing_rules( $request ) {
		$params = $request->get_json_params();
		$rules  = isset( $params['rules'] ) ? $params['rules'] : array();

		// Validate each rule.
		$clean_rules = array();
		foreach ( $rules as $rule ) {
			if ( isset( $rule['path_contains'] ) && isset( $rule['price_usd'] ) ) {
				$clean_rules[] = array(
					'path_contains' => sanitize_text_field( $rule['path_contains'] ),
					'price_usd'     => sanitize_text_field( $rule['price_usd'] ),
				);
			}
		}

		update_option( 'xenarch_pricing_rules', wp_json_encode( $clean_rules ) );

		// Sync to platform.
		$site_id = get_option( 'xenarch_site_id', '' );
		if ( ! empty( $site_id ) ) {
			$api_rules = array();
			foreach ( $clean_rules as $rule ) {
				$api_rules[] = array(
					'path'      => '*' . $rule['path_contains'] . '*',
					'price_usd' => (float) $rule['price_usd'],
				);
			}

			$this->api->update_pricing(
				$site_id,
				(float) get_option( 'xenarch_default_price', '0.003' ),
				$api_rules
			);
		}

		return new WP_REST_Response( array( 'rules' => $clean_rules ), 200 );
	}

	// ------------------------------------------------------------------
	// Stats / Transactions (platform proxy)
	// ------------------------------------------------------------------

	/**
	 * GET /stats — proxy to platform stats endpoint.
	 *
	 * @return WP_REST_Response
	 */
	public function get_stats() {
		$site_id = get_option( 'xenarch_site_id', '' );
		if ( empty( $site_id ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Site not configured.' ),
				400
			);
		}

		$result = $this->api->get_stats( $site_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				502
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /transactions — proxy to platform transactions endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_transactions( $request ) {
		$site_id = get_option( 'xenarch_site_id', '' );
		if ( empty( $site_id ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Site not configured.' ),
				400
			);
		}

		$params = array();

		$period = $request->get_param( 'period' );
		if ( ! empty( $period ) ) {
			$params['period'] = sanitize_text_field( $period );
		}

		$page = $request->get_param( 'page' );
		if ( ! empty( $page ) ) {
			$params['page'] = absint( $page );
		}

		$per_page = $request->get_param( 'per_page' );
		if ( ! empty( $per_page ) ) {
			$params['per_page'] = min( absint( $per_page ), 100 );
		}

		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) && in_array( $status, array( 'paid', 'blocked', 'all' ), true ) ) {
			$params['status'] = $status;
		}

		$result = $this->api->get_transactions( $site_id, $params );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				502
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	// ------------------------------------------------------------------
	// Balance / Category breakdown / Withdraw
	// ------------------------------------------------------------------

	/**
	 * GET /balance — wallet balance for xenarch-managed wallets.
	 *
	 * @return WP_REST_Response
	 */
	public function get_balance() {
		$site_id = get_option( 'xenarch_site_id', '' );
		if ( empty( $site_id ) ) {
			return new WP_REST_Response( array( 'balance_usd' => '0.00' ), 200 );
		}

		$result = $this->api->get_balance( $site_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'balance_usd' => '0.00' ), 200 );
		}

		return new WP_REST_Response(
			array( 'balance_usd' => isset( $result['balance_usd'] ) ? $result['balance_usd'] : '0.00' ),
			200
		);
	}

	/**
	 * GET /category-breakdown — earnings aggregated by bot category.
	 *
	 * @return WP_REST_Response
	 */
	public function get_category_breakdown() {
		$site_id = get_option( 'xenarch_site_id', '' );
		if ( empty( $site_id ) ) {
			return new WP_REST_Response( array( 'categories' => array() ), 200 );
		}

		$result = $this->api->get_category_breakdown( $site_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'categories' => array() ), 200 );
		}

		return new WP_REST_Response(
			array( 'categories' => isset( $result['categories'] ) ? $result['categories'] : array() ),
			200
		);
	}

	/**
	 * POST /withdraw — initiate USDC withdrawal for xenarch wallets.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_withdraw( $request ) {
		$params = $request->get_json_params();

		$to_address = isset( $params['to_address'] ) ? sanitize_text_field( $params['to_address'] ) : '';
		$network    = isset( $params['network'] ) ? sanitize_text_field( $params['network'] ) : 'Base';
		$amount_usd = isset( $params['amount_usd'] ) ? sanitize_text_field( $params['amount_usd'] ) : '';

		if ( empty( $to_address ) || empty( $amount_usd ) ) {
			return new WP_REST_Response( array( 'error' => 'Address and amount are required.' ), 400 );
		}

		$site_id = get_option( 'xenarch_site_id', '' );
		if ( empty( $site_id ) ) {
			return new WP_REST_Response( array( 'error' => 'Site not configured.' ), 400 );
		}

		$result = $this->api->withdraw( $site_id, $to_address, $network, $amount_usd );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				502
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'tx_hash' => isset( $result['tx_hash'] ) ? $result['tx_hash'] : null,
			),
			200
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Get all Xenarch settings as an array.
	 *
	 * @return array
	 */
	private function get_all_settings() {
		return array(
			'api_key'              => ! empty( get_option( 'xenarch_api_key', '' ) ),
			'site_id'              => get_option( 'xenarch_site_id', '' ),
			'site_token'           => get_option( 'xenarch_site_token', '' ),
			'email'                => get_option( 'xenarch_email', '' ),
			'default_price'        => get_option( 'xenarch_default_price', '0.003' ),
			'payout_wallet'        => get_option( 'xenarch_payout_wallet', '' ),
			'gate_unknown_traffic' => get_option( 'xenarch_gate_unknown_traffic', '1' ),
			'domain'               => wp_parse_url( get_site_url(), PHP_URL_HOST ),
			'is_registered'        => ! empty( get_option( 'xenarch_api_key', '' ) ),
			'has_site'             => ! empty( get_option( 'xenarch_site_id', '' ) ) && ! empty( get_option( 'xenarch_site_token', '' ) ),
			'bot_signature_count'  => count( Xenarch_Bot_Detect::get_signatures() ) + count( Xenarch_Bot_Detect::get_fetcher_signatures() ),
			'pay_json_url'         => get_site_url() . '/.well-known/pay.json',
			'xenarch_md_url'       => get_site_url() . '/.well-known/xenarch.md',
			'wallet_type'          => get_option( 'xenarch_wallet_type', '' ),
		);
	}
}

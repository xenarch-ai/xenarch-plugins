<?php
/**
 * Xenarch admin settings page.
 *
 * Registers the Settings > Xenarch page and enqueues the React admin panel.
 * All UI logic lives in admin-ui/dist/xenarch-admin.js.
 *
 * @package Xenarch
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Admin
 */
class Xenarch_Admin {

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 3 );
	}

	/**
	 * Add type="module" to the React admin script tag.
	 *
	 * @param string $tag    Script HTML tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 * @return string
	 */
	public function add_module_type( $tag, $handle, $src ) {
		if ( 'xenarch-admin' !== $handle ) {
			return $tag;
		}
		// Replace the script type attribute to make it an ES module.
		return str_replace( ' src=', ' type="module" src=', $tag );
	}

	/**
	 * Register the settings page under Settings.
	 */
	public function add_menu_page() {
		add_options_page(
			__( 'Xenarch Dashboard', 'xenarch' ),
			__( 'Xenarch', 'xenarch' ),
			'manage_options',
			'xenarch',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue React admin assets on the settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_xenarch' !== $hook_suffix ) {
			return;
		}

		$dist_url = XENARCH_PLUGIN_URL . 'admin-ui/dist/';

		wp_enqueue_style(
			'xenarch-admin',
			$dist_url . 'xenarch-admin.css',
			array(),
			XENARCH_VERSION
		);

		wp_enqueue_script(
			'xenarch-admin',
			$dist_url . 'xenarch-admin.js',
			array(),
			XENARCH_VERSION,
			true
		);
	}

	/**
	 * Get the Reown (WalletConnect) project ID from the platform API.
	 * Cached as a WordPress transient for 24 hours.
	 *
	 * @return string Project ID or empty string on failure.
	 */
	private function get_wc_project_id() {
		$cached = get_transient( 'xenarch_wc_project_id' );
		if ( false !== $cached ) {
			return $cached;
		}

		$api    = new Xenarch_Api();
		$result = $api->get_config();

		if ( ! is_wp_error( $result ) && ! empty( $result['wc_project_id'] ) ) {
			set_transient( 'xenarch_wc_project_id', $result['wc_project_id'], DAY_IN_SECONDS );
			return $result['wc_project_id'];
		}

		return '';
	}

	/**
	 * Render the settings page — just a mount point for React.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$config = array(
			'restUrl'     => rest_url( 'xenarch/v1' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'settings'    => $this->get_initial_settings(),
			'pluginUrl'   => XENARCH_PLUGIN_URL,
			'version'     => XENARCH_VERSION,
			'wcProjectId' => $this->get_wc_project_id(),
		);
		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<script>window.xenarchAdmin = ' . wp_json_encode( $config ) . ';</script>';
		echo '<div id="xenarch-admin"></div></div>';
	}

	/**
	 * Get initial settings snapshot for the React app.
	 *
	 * @return array
	 */
	private function get_initial_settings() {
		$api_key    = get_option( 'xenarch_api_key', '' );
		$site_id    = get_option( 'xenarch_site_id', '' );
		$site_token = get_option( 'xenarch_site_token', '' );

		return array(
			'api_key'              => ! empty( $api_key ),
			'site_id'              => $site_id,
			'site_token'           => $site_token,
			'email'                => get_option( 'xenarch_email', '' ),
			'default_price'        => get_option( 'xenarch_default_price', '0.003' ),
			'payout_wallet'        => get_option( 'xenarch_payout_wallet', '' ),
			'gate_unknown_traffic' => get_option( 'xenarch_gate_unknown_traffic', '1' ),
			'domain'               => wp_parse_url( get_site_url(), PHP_URL_HOST ),
			'is_registered'        => ! empty( $api_key ),
			'has_site'             => ! empty( $site_id ) && ! empty( $site_token ),
			'bot_signature_count'  => count( Xenarch_Bot_Detect::get_signatures() ) + count( Xenarch_Bot_Detect::get_fetcher_signatures() ),
			'pay_json_url'         => get_site_url() . '/.well-known/pay.json',
			'xenarch_md_url'       => get_site_url() . '/.well-known/xenarch.md',
			'wallet_type'          => get_option( 'xenarch_wallet_type', '' ),
		);
	}
}

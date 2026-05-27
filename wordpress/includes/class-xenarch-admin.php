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
			__( '/xenarch dashboard', 'xenarch' ),
			__( '/xenarch', 'xenarch' ),
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

		// Add class to body for full-bleed background styling.
		add_filter( 'admin_body_class', function( $classes ) {
			return $classes . ' xenarch-page';
		} );

		$dist_url = XENARCH_PLUGIN_URL . 'admin-ui/dist/';

		wp_enqueue_style(
			'xenarch-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&family=Space+Grotesk:wght@300;500;600&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'xenarch-admin',
			$dist_url . 'xenarch-admin.css',
			array( 'xenarch-fonts' ),
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
	 * Render the settings page — just a mount point for React.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$config = array(
			'restUrl'   => rest_url( 'xenarch/v1' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'settings'  => $this->get_initial_settings(),
			'pluginUrl' => XENARCH_PLUGIN_URL,
			'version'   => XENARCH_VERSION,
		);
		echo '<div class="wrap xenarch-wrap">';
		echo '<div class="xenarch-page-container">';
		echo '<div class="xenarch-page-header">';
		echo '<span class="xenarch-page-logo">/xenarch</span>';
		echo '<span class="xenarch-page-version">v' . esc_html( XENARCH_VERSION ) . '</span>';
		echo '<button class="xenarch-page-theme-toggle" onclick="xenarchToggleTheme()" title="Toggle theme" style="margin-left:auto;">';
		echo '<svg class="xn-icon-sun" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="3"/><path d="M8 1.5v1.5M8 13v1.5M2.75 2.75l1.06 1.06M12.19 12.19l1.06 1.06M1.5 8H3M13 8h1.5M2.75 13.25l1.06-1.06M12.19 3.81l1.06-1.06"/></svg>';
		echo '<svg class="xn-icon-moon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.2 9.5a5.5 5.5 0 01-7-7 5.5 5.5 0 106.7 6.7z"/></svg>';
		echo '</button>';
		echo '</div>';
		echo '<p class="xenarch-page-subtitle">Charge the bots that take. Let through the ones that help.</p>';
		echo '<script>window.xenarchAdmin = ' . wp_json_encode( $config ) . ';';
		echo 'function xenarchToggleTheme(){';
		echo 'var el=document.getElementById("xenarch-admin");if(!el)return;';
		echo 'var t=el.getAttribute("data-theme")==="light"?"dark":"light";';
		echo 'el.setAttribute("data-theme",t);localStorage.setItem("xenarch-theme",t);';
		echo 'document.body.classList.toggle("xenarch-light",t==="light");';
		echo '}';
		echo '</script>';
		echo '<div id="xenarch-admin"></div>';
		echo '</div></div>';
	}

	/**
	 * Get initial settings snapshot for the React app.
	 *
	 * @return array
	 */
	/**
	 * Bootstrap state for the React app. Mirrors
	 * ``Xenarch_Rest::snapshot()`` — keep in sync. Only fields the React
	 * shell needs to render its initial frame: the rest is fetched live
	 * from the platform once the admin tabs mount.
	 */
	private function get_initial_settings() {
		$site_id    = get_option( 'xenarch_site_id', '' );
		$site_token = get_option( 'xenarch_site_token', '' );

		return array(
			'site_id'             => (string) $site_id,
			'site_token'          => (string) $site_token,
			'domain'              => wp_parse_url( get_site_url(), PHP_URL_HOST ),
			'has_site'            => ! empty( $site_token ),
			'bot_signature_count' => count( Xenarch_Bot_Detect::get_signatures() )
				+ count( Xenarch_Bot_Detect::get_fetcher_signatures() ),
			'pay_json_url'        => get_site_url() . '/.well-known/pay.json',
			'xenarch_md_url'      => get_site_url() . '/.well-known/xenarch.md',
		);
	}
}

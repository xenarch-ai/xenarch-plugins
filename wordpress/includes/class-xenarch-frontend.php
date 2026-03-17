<?php
/**
 * Xenarch frontend script loader.
 *
 * Enqueues l.js on all public-facing pages when a site token is configured.
 *
 * @package Xenarch
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Frontend
 */
class Xenarch_Frontend {

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_snippet' ) );
	}

	/**
	 * Enqueue the l.js snippet on all frontend pages.
	 *
	 * Only loads if a site token has been configured via the admin settings.
	 * The script is loaded asynchronously to avoid blocking page rendering.
	 */
	public function enqueue_snippet() {
		$site_token = get_option( 'xenarch_site_token', '' );

		// Do not load if site is not configured.
		if ( empty( $site_token ) ) {
			return;
		}

		/**
		 * Filter the Xenarch CDN base URL.
		 *
		 * @param string $cdn_url Default CDN URL.
		 */
		$cdn_url = apply_filters( 'xenarch_cdn_url', 'https://cdn.xenarch.bot' );

		$script_url = trailingslashit( $cdn_url ) . 'l.js';

		wp_enqueue_script(
			'xenarch-ljs',
			$script_url,
			array(),
			null, // Version managed by CDN, not by WP.
			array(
				'strategy' => 'defer',
				'in_footer' => true,
			)
		);

		// Add data-site-id attribute via script_loader_tag filter.
		add_filter( 'script_loader_tag', array( $this, 'add_data_attributes' ), 10, 2 );
	}

	/**
	 * Add data-site-id attribute to the l.js script tag.
	 *
	 * @param string $tag    The full <script> tag.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 */
	public function add_data_attributes( $tag, $handle ) {
		if ( 'xenarch-ljs' !== $handle ) {
			return $tag;
		}

		$site_token = get_option( 'xenarch_site_token', '' );

		if ( empty( $site_token ) ) {
			return $tag;
		}

		// Insert data-site-id attribute into the script tag.
		$tag = str_replace(
			' src=',
			' data-site-id="' . esc_attr( $site_token ) . '" src=',
			$tag
		);

		return $tag;
	}
}

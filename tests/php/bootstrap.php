<?php
/**
 * Minimal test bootstrap for Xenarch plugin classes.
 */

define( 'ABSPATH', __DIR__ );
define( 'XENARCH_BROWSER_PROOF_SECRET', 'test-browser-proof-secret' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			return trim( (string) $value );
		}

		return '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

require_once dirname( __DIR__, 2 ) . '/wordpress/includes/class-xenarch-bot-detect.php';
require_once dirname( __DIR__, 2 ) . '/wordpress/includes/class-xenarch-access-token.php';
require_once dirname( __DIR__, 2 ) . '/wordpress/includes/class-xenarch-browser-proof.php';
require_once dirname( __DIR__, 2 ) . '/wordpress/includes/class-xenarch-gate-response.php';

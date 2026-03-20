<?php
/**
 * Xenarch gate response helpers.
 *
 * @package Xenarch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Gate_Response
 */
class Xenarch_Gate_Response {

	/**
	 * Build a safe fallback gate payload when upstream gate creation fails.
	 *
	 * @param string $request_path      Protected request path.
	 * @param string $detection_method  Detection method.
	 * @return array
	 */
	public static function build_fallback_gate_payload( $request_path, $detection_method ) {
		return array(
			'xenarch'          => true,
			'requested_path'   => $request_path,
			'detection_method' => $detection_method,
			'message'          => 'Payment required for automated access.',
		);
	}

	/**
	 * Build the browser proof challenge page.
	 *
	 * @param string $request_path Protected request path.
	 * @param string $cookie_value Signed proof cookie value.
	 * @return string
	 */
	public static function build_challenge_html( $request_path, $cookie_value ) {
		$cookie_name   = Xenarch_Browser_Proof::COOKIE_NAME;
		$target        = self::json_encode_for_script( $request_path );
		$cookie        = self::json_encode_for_script( $cookie_value );
		$cookie_name_js = self::json_encode_for_script( $cookie_name );

		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>Verifying browser</title></head><body><noscript>JavaScript is required to continue.</noscript>'
			. '<script>(function(){var cookieName=' . $cookie_name_js . ';var cookieValue=' . $cookie . ';var target=' . $target . ';'
			. 'document.cookie=cookieName+"="+cookieValue+"; Path=/; Max-Age=' . (int) Xenarch_Browser_Proof::DEFAULT_TTL . '; SameSite=Lax";'
			. 'window.location.replace(target);})();</script>'
			. '<p>Verifying browser...</p></body></html>';
	}

	/**
	 * Encode a string for safe inclusion in inline script.
	 *
	 * @param string $value Value to encode.
	 * @return string
	 */
	private static function json_encode_for_script( $value ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( (string) $value, JSON_UNESCAPED_SLASHES );
		}

		return json_encode( (string) $value, JSON_UNESCAPED_SLASHES );
	}
}

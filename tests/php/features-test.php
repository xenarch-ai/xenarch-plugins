<?php
/**
 * XEN-684 — Features switch: pure helper tests.
 *
 * Standalone (no WordPress): only exercises the helpers that make no
 * WordPress calls. Run with `php tests/php/features-test.php`.
 */

define( 'ABSPATH', __DIR__ );
require_once dirname( __DIR__, 2 ) . '/wordpress/includes/class-xenarch-features.php';

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new Exception(
			$message . "\nExpected: " . var_export( $expected, true ) . "\nActual:   " . var_export( $actual, true )
		);
	}
}

function run_test( $name, $callback ) {
	try {
		$callback();
		echo "PASS {$name}\n";
	} catch ( Exception $exception ) {
		echo "FAIL {$name}\n";
		echo $exception->getMessage() . "\n";
		exit( 1 );
	}
}

run_test(
	'unset option falls back to the default',
	function () {
		assert_same( true, Xenarch_Features::to_bool( false, true ), 'Unset + default on is on.' );
		assert_same( false, Xenarch_Features::to_bool( false, false ), 'Unset + default off is off.' );
	}
);

run_test(
	'stored values win over the default',
	function () {
		assert_same( true, Xenarch_Features::to_bool( '1', false ), '"1" is on.' );
		assert_same( false, Xenarch_Features::to_bool( '0', true ), '"0" is off even with default on.' );
		assert_same( false, Xenarch_Features::to_bool( '', true ), 'Empty string is off.' );
	}
);

run_test(
	'woocommerce without gating preselects checkout only',
	function () {
		assert_same( array( 'gating' => false, 'checkout' => true ), Xenarch_Features::suggest( true, false ), 'Shop with no gating set up.' );
	}
);

run_test(
	'woocommerce with gating set up preselects both',
	function () {
		assert_same( array( 'gating' => true, 'checkout' => true ), Xenarch_Features::suggest( true, true ), 'Shop that already gates.' );
	}
);

run_test(
	'no woocommerce preselects gating only',
	function () {
		assert_same( array( 'gating' => true, 'checkout' => false ), Xenarch_Features::suggest( false, false ), 'Blog without WooCommerce.' );
		assert_same( array( 'gating' => true, 'checkout' => false ), Xenarch_Features::suggest( false, true ), 'Paired blog without WooCommerce.' );
	}
);

run_test(
	'tagline follows the features that are on',
	function () {
		assert_same( 'Charge the bots that take. Let through the ones that help.', Xenarch_Features::tagline_for( true, false ), 'Gating only keeps the v1.12 tagline.' );
		assert_same( 'Take USDC payments at your WooCommerce checkout.', Xenarch_Features::tagline_for( false, true ), 'Checkout only never mentions bots.' );
		assert_same( 'Charge the bots that take, and take USDC at checkout.', Xenarch_Features::tagline_for( true, true ), 'Both.' );
		assert_same( 'Bot gating and crypto checkout for WordPress.', Xenarch_Features::tagline_for( false, false ), 'Neither.' );
	}
);

run_test(
	'checkout can only be turned on with woocommerce active',
	function () {
		assert_same( '', Xenarch_Features::update_problem( true, true ), 'On with WooCommerce is fine.' );
		assert_same( '', Xenarch_Features::update_problem( false, false ), 'Off without WooCommerce is fine.' );
		assert_same( '', Xenarch_Features::update_problem( null, false ), 'Unchanged checkout is fine.' );
		assert_same( true, '' !== Xenarch_Features::update_problem( true, false ), 'On without WooCommerce gives a reason.' );
	}
);

<?php
/**
 * XEN-678 phase 2 — Tron helpers of the WooCommerce gateway.
 *
 * Standalone (no WordPress): get_option is stubbed. Run with
 * `php tests/php/woo-tron-test.php`.
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['xen_test_options'] = array();
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['xen_test_options'] ) ? $GLOBALS['xen_test_options'][ $name ] : $default;
}

require_once dirname( __DIR__, 2 ) . '/wordpress/includes/class-xenarch-woo.php';

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

$tron_store = 'TEPc1dsFJ5jNuYf7arvd9rpSA9ZSdZnW3q';
$tron_tx    = '50f55d0195556ac5f5cec22a1fbe41300b415d9b7aaf52d750d1a32d966181b1';
$evm_tx     = '0x' . str_repeat( 'ab', 32 );

run_test(
	'Tron is USDT only, with a Tronscan link',
	function () use ( $tron_tx ) {
		assert_same( array( 'USDT' ), Xenarch_Woo::NETWORKS['tron'][1], 'Tron takes USDT only.' );
		assert_same( 'Tron', Xenarch_Woo::network_name( 'tron' ), 'Name.' );
		assert_same( 'https://tronscan.org/#/transaction/' . $tron_tx, Xenarch_Woo::explorer_url( 'tron', $tron_tx ), 'Explorer.' );
	}
);

run_test(
	'Tron address shape',
	function () use ( $tron_store ) {
		assert_same( true, Xenarch_Woo::looks_like_tron_address( $tron_store ), 'Real address.' );
		assert_same( false, Xenarch_Woo::looks_like_tron_address( strtolower( $tron_store ) ), 'Lowercased is not one.' );
		assert_same( false, Xenarch_Woo::looks_like_tron_address( '0x' . str_repeat( '1', 40 ) ), 'EVM address.' );
		assert_same( false, Xenarch_Woo::looks_like_tron_address( 'T0' . str_repeat( '1', 32 ) ), 'No 0 in base58.' );
		assert_same( false, Xenarch_Woo::looks_like_tron_address( $tron_store . '1' ), 'Too long.' );
		assert_same( false, Xenarch_Woo::looks_like_tron_address( null ), 'Null.' );
	}
);

run_test(
	'tx hashes are read per network',
	function () use ( $tron_tx, $evm_tx ) {
		assert_same( $tron_tx, Xenarch_Woo::normalize_tx( 'tron', $tron_tx ), 'Tron txid as is.' );
		assert_same( $tron_tx, Xenarch_Woo::normalize_tx( 'tron', '0x' . strtoupper( $tron_tx ) ), 'Tron txid with 0x / uppercase.' );
		assert_same( '', Xenarch_Woo::normalize_tx( 'tron', substr( $tron_tx, 1 ) ), 'Short Tron txid.' );
		assert_same( $evm_tx, Xenarch_Woo::normalize_tx( 'bnb', '0x' . strtoupper( substr( $evm_tx, 2 ) ) ), 'EVM hash lowercased.' );
		assert_same( '', Xenarch_Woo::normalize_tx( 'base', $tron_tx ), 'EVM needs 0x.' );
	}
);

run_test(
	'a Tron payment must reach the store Tron address, exactly',
	function () use ( $tron_store ) {
		$GLOBALS['xen_test_options'] = array(
			Xenarch_Woo::OPT_PAY_TO       => '0xAbC0000000000000000000000000000000000001',
			Xenarch_Woo::OPT_TRON_ADDRESS => $tron_store,
		);
		assert_same( $tron_store, Xenarch_Woo::pay_to_for( 'tron' ), 'Tron address, case kept.' );
		assert_same( '0xabc0000000000000000000000000000000000001', Xenarch_Woo::pay_to_for( 'ethereum' ), 'EVM wallet lowercased.' );
		$GLOBALS['xen_test_options'] = array();
		assert_same( '', Xenarch_Woo::pay_to_for( 'tron' ), 'No Tron address: nothing matches.' );
	}
);

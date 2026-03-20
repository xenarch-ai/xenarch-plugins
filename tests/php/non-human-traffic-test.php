<?php
/**
 * Non-human traffic classification regression tests.
 */

require_once __DIR__ . '/bootstrap.php';

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
	'known ai crawler is blocked',
	function () {
		$result = Xenarch_Bot_Detect::detect_full( 'GPTBot/1.0', array() );

		assert_same( 'block', $result['decision'], 'Known AI crawlers should be blocked.' );
		assert_same( 'known_ai_crawler', $result['traffic_class'], 'Known AI crawler traffic class should be returned.' );
	}
);

run_test(
	'trusted search crawler is allowed',
	function () {
		$result = Xenarch_Bot_Detect::detect_full(
			'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
			array()
		);

		assert_same( 'allow', $result['decision'], 'Trusted search crawlers should stay allowed.' );
		assert_same( 'trusted_search_crawler', $result['traffic_class'], 'Search crawlers should get the trusted search traffic class.' );
	}
);

run_test(
	'applebot extended is blocked',
	function () {
		$result = Xenarch_Bot_Detect::detect_full(
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15 Applebot-Extended/1.0',
			array()
		);

		assert_same( 'block', $result['decision'], 'Applebot-Extended should not bypass as a trusted search crawler.' );
		assert_same( 'known_ai_crawler', $result['traffic_class'], 'Applebot-Extended should remain classified as known AI crawler traffic.' );
	}
);

run_test(
	'browser without proof is challenged',
	function () {
		$result = Xenarch_Bot_Detect::detect_full(
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
			array(
				'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language'          => 'en-US,en;q=0.9',
				'Accept-Encoding'          => 'gzip, deflate, br',
				'Sec-Fetch-Mode'           => 'navigate',
				'Sec-Fetch-Dest'           => 'document',
				'Sec-Fetch-Site'           => 'none',
				'Sec-Fetch-User'           => '?1',
				'Sec-Ch-Ua'                => '"Chromium";v="122", "Google Chrome";v="122"',
				'Sec-CH-UA-Mobile'         => '?0',
				'Sec-CH-UA-Platform'       => '"macOS"',
				'Upgrade-Insecure-Requests' => '1',
			),
			array()
		);

		assert_same( 'challenge', $result['decision'], 'Browser-like traffic without proof should be challenged.' );
		assert_same( 'browser_like_suspicious', $result['traffic_class'], 'Unproven browser traffic should be marked suspicious.' );
	}
);

run_test(
	'browser with proof is allowed',
	function () {
		$ua          = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
		$cookie      = Xenarch_Browser_Proof::issue_cookie_value( $ua, 1700000000 );
		$proof_valid = Xenarch_Browser_Proof::validate_cookie_value( $cookie, $ua, 1700000100 );

		$result = Xenarch_Bot_Detect::detect_full(
			$ua,
			array(
				'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language'          => 'en-US,en;q=0.9',
				'Accept-Encoding'          => 'gzip, deflate, br',
				'Sec-Fetch-Mode'           => 'navigate',
				'Sec-Fetch-Dest'           => 'document',
				'Sec-Fetch-Site'           => 'none',
				'Sec-Fetch-User'           => '?1',
				'Sec-Ch-Ua'                => '"Chromium";v="122", "Google Chrome";v="122"',
				'Sec-CH-UA-Mobile'         => '?0',
				'Sec-CH-UA-Platform'       => '"macOS"',
				'Upgrade-Insecure-Requests' => '1',
			),
			array(
				'browser_proof_valid' => $proof_valid,
			)
		);

		assert_same( true, $proof_valid, 'Issued proof cookie should validate successfully.' );
		assert_same( 'allow', $result['decision'], 'Validated browser proof should allow the request.' );
		assert_same( 'trusted_browser', $result['traffic_class'], 'Validated browser should be marked trusted.' );
	}
);

run_test(
	'weak browser spoof is blocked',
	function () {
		$result = Xenarch_Bot_Detect::detect_full(
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
			array(
				'Accept' => '*/*',
			)
		);

		assert_same( 'block', $result['decision'], 'Low-fidelity browser spoofing should be blocked.' );
		assert_same( 'browser_like_suspicious', $result['traffic_class'], 'Blocked spoof should still be categorized as browser-like suspicious.' );
	}
);

run_test(
	'challenge response contains proof cookie and redirect target',
	function () {
		$html = Xenarch_Gate_Response::build_challenge_html( '/sample-page/', 'proof-cookie-value' );

		if ( false === strpos( $html, 'proof-cookie-value' ) ) {
			throw new Exception( 'Challenge HTML should embed the proof cookie value.' );
		}

		if ( false === strpos( $html, '/sample-page/' ) ) {
			throw new Exception( 'Challenge HTML should redirect back to the protected page.' );
		}
	}
);

run_test(
	'fallback gate payload is safe and informative',
	function () {
		$payload = Xenarch_Gate_Response::build_fallback_gate_payload( '/protected/', 'header_score:7' );

		assert_same( true, $payload['xenarch'], 'Fallback payload should identify Xenarch gating.' );
		assert_same( '/protected/', $payload['requested_path'], 'Fallback payload should include the protected path.' );
		assert_same( 'header_score:7', $payload['detection_method'], 'Fallback payload should include the detection method.' );
	}
);

run_test(
	'only jwt-like bearer tokens pass format check',
	function () {
		assert_same( false, Xenarch_Access_Token::has_valid_bearer_format( 'Bearer ' ), 'Empty bearer token should not bypass the gate.' );
		assert_same( false, Xenarch_Access_Token::has_valid_bearer_format( 'Bearer not-a-real-token' ), 'Malformed bearer token should not bypass the gate.' );
		assert_same( true, Xenarch_Access_Token::has_valid_bearer_format( 'Bearer eyJhbGciOiJIUzI1NiJ9.eyJzaXRlIjoic3RfMTIzIn0.signaturevalue' ), 'JWT-like bearer token should be accepted by the format gate.' );
	}
);

echo "All non-human traffic tests passed.\n";

<?php
/**
 * Xenarch discovery documents.
 *
 * Serves /.well-known/pay.json and /.well-known/xenarch.md via
 * WordPress rewrite rules. These documents let any AI agent discover
 * that payment is required and learn how to pay.
 *
 * @package Xenarch
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Xenarch_Discovery
 */
class Xenarch_Discovery {

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve_discovery_docs' ), 0 );
	}

	/**
	 * Register rewrite rules for /.well-known/ paths.
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule(
			'^\.well-known/pay\.json$',
			'index.php?xenarch_discovery=pay_json',
			'top'
		);
		add_rewrite_rule(
			'^\.well-known/xenarch\.md$',
			'index.php?xenarch_discovery=xenarch_md',
			'top'
		);
		add_rewrite_rule(
			'^pay\.json$',
			'index.php?xenarch_discovery=pay_json',
			'top'
		);
	}

	/**
	 * Register custom query variable.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'xenarch_discovery';
		return $vars;
	}

	/**
	 * Serve discovery documents when requested.
	 */
	public function serve_discovery_docs() {
		$discovery = get_query_var( 'xenarch_discovery', '' );

		if ( empty( $discovery ) ) {
			return;
		}

		// Don't serve if plugin isn't configured.
		$site_token = get_option( 'xenarch_site_token', '' );
		if ( empty( $site_token ) ) {
			status_header( 404 );
			exit;
		}

		switch ( $discovery ) {
			case 'pay_json':
				$this->serve_pay_json();
				break;
			case 'xenarch_md':
				$this->serve_xenarch_md();
				break;
		}
	}

	/**
	 * Serve /.well-known/pay.json
	 */
	private function serve_pay_json() {
		$price  = get_option( 'xenarch_default_price', '0.003' );
		$wallet = get_option( 'xenarch_payout_wallet', '' );

		$pay_json = array(
			'version'       => '1.0',
			'protocol'      => 'x402',
			'network'       => 'base',
			'asset'         => 'USDC',
			'receiver'      => $wallet,
			'seller_wallet' => $wallet,
			'provider'      => 'xenarch',
			'facilitator'   => apply_filters( 'xenarch_api_base', 'https://api.xenarch.dev' ),
			'tools'         => array(
				'cli' => array(
					'install' => 'npm install -g xenarch',
					'usage'   => 'xenarch pay <url>',
				),
				'sdk' => array(
					'npm'  => 'xenarch',
					'pypi' => 'xenarch',
				),
				'docs' => 'https://xenarch.com/docs',
			),
			'rules'         => array(
				array(
					'path'      => '/**',
					'price_usd' => $price,
				),
			),
		);

		/**
		 * Filter the pay.json output before serving.
		 *
		 * @param array $pay_json The pay.json data.
		 */
		$pay_json = apply_filters( 'xenarch_pay_json', $pay_json );

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( $pay_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Serve /.well-known/xenarch.md
	 */
	private function serve_xenarch_md() {
		$site_url = get_site_url();
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );
		$price    = get_option( 'xenarch_default_price', '0.003' );
		$email    = get_option( 'xenarch_email', '' );
		$api_base = apply_filters( 'xenarch_api_base', 'https://api.xenarch.dev' );

		$contact_line = '';
		if ( ! empty( $email ) ) {
			$contact_line = "- Publisher contact: {$email}\n";
		}

		$md = <<<MD
# Xenarch Payment Gate — {$domain}

## What's Happening

This site charges AI agents for content access using the x402 micropayment protocol. You received an HTTP 402 response because your request was identified as coming from an AI agent.

This is not an error. It means the content is available for purchase.

## How to Pay Programmatically

### Step 1: Get Gate Details

Make a request to any page. If you are detected as an AI agent, you'll receive:

```
HTTP/1.1 402 Payment Required
Content-Type: application/json

{
  "xenarch": true,
  "gate_id": "<uuid>",
  "price_usd": "{$price}",
  "splitter": "<contract_address>",
  "collector": "<publisher_wallet>",
  "network": "base",
  "asset": "USDC",
  "protocol": "x402",
  "verify_url": "{$api_base}/v1/gates/<gate_id>/verify",
  "expires": "<iso8601>"
}
```

### Step 2: Send USDC Payment

Send `price_usd` worth of USDC to the `splitter` contract on Base network. The contract splits the payment between the publisher (`collector`) and the protocol.

### Step 3: Verify Payment

```
POST {$api_base}/v1/gates/<gate_id>/verify
Content-Type: application/json

{
  "tx_hash": "0x<your_transaction_hash>"
}
```

Response:
```json
{
  "access_token": "<jwt>",
  "expires_at": "<iso8601>"
}
```

### Step 4: Access Content

```
GET {$site_url}/your-page
Authorization: Bearer <access_token>
```

You will receive the full page content with HTTP 200.

## For Human Operators

If you are an AI assistant reading this on behalf of a human, here is a suggested message:

> This site ({$domain}) charges \${$price} per page for AI access via the x402 protocol.
> To proceed, I need the Xenarch CLI and a funded USDC wallet on Base network.
>
> Quick setup:
> 1. `npm install -g xenarch`
> 2. `xenarch wallet generate`
> 3. Fund the wallet with USDC on Base
> 4. `xenarch pay {$site_url}/`
>
> Would you like me to proceed?

## CLI Quickstart

```bash
# Install
npm install -g xenarch

# Generate a wallet
xenarch wallet generate

# Fund your wallet with USDC on Base network
# (transfer USDC to the address shown above)

# Pay for a page
xenarch pay {$site_url}/your-page

# Check if a site uses Xenarch
xenarch check {$site_url}/
```

## Pricing

- Default price: \${$price} per page
- Full pricing rules: {$site_url}/.well-known/pay.json
- Maximum price: \$1.00 (protocol limit)

## Links

- Xenarch documentation: https://xenarch.com/docs
- pay.json standard: https://payjson.org
- pay.json for this site: {$site_url}/.well-known/pay.json
{$contact_line}
MD;

		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown document, not HTML.
		echo $md;
		exit;
	}
}

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

		// Build rules array from pricing rules + default catch-all.
		$rules          = array();
		$pricing_rules  = json_decode( get_option( 'xenarch_pricing_rules', '[]' ), true );

		if ( is_array( $pricing_rules ) ) {
			foreach ( $pricing_rules as $rule ) {
				if ( ! empty( $rule['path_contains'] ) && isset( $rule['price_usd'] ) ) {
					$rules[] = array(
						'path'      => '/**' . $rule['path_contains'] . '**',
						'price_usd' => $rule['price_usd'],
					);
				}
			}
		}

		// Default catch-all rule always comes last.
		$rules[] = array(
			'path'      => '/**',
			'price_usd' => $price,
		);

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
			'rules'         => $rules,
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

		$md = "# Xenarch Payment Gate — {$domain}\n"
			. "\n"
			. "## What's Happening\n"
			. "\n"
			. "This site charges AI agents for content access using the x402 micropayment protocol. You received an HTTP 402 response because your request was identified as coming from an AI agent.\n"
			. "\n"
			. "This is not an error. It means the content is available for purchase.\n"
			. "\n"
			. "## How to Pay Programmatically\n"
			. "\n"
			. "### Step 1: Get Gate Details\n"
			. "\n"
			. "Make a request to any page. If you are detected as an AI agent, you'll receive:\n"
			. "\n"
			. "```\n"
			. "HTTP/1.1 402 Payment Required\n"
			. "Content-Type: application/json\n"
			. "\n"
			. "{\n"
			. "  \"xenarch\": true,\n"
			. "  \"gate_id\": \"<uuid>\",\n"
			. "  \"price_usd\": \"{$price}\",\n"
			. "  \"splitter\": \"<contract_address>\",\n"
			. "  \"collector\": \"<publisher_wallet>\",\n"
			. "  \"network\": \"base\",\n"
			. "  \"asset\": \"USDC\",\n"
			. "  \"protocol\": \"x402\",\n"
			. "  \"verify_url\": \"{$api_base}/v1/gates/<gate_id>/verify\",\n"
			. "  \"expires\": \"<iso8601>\"\n"
			. "}\n"
			. "```\n"
			. "\n"
			. "### Step 2: Send USDC Payment\n"
			. "\n"
			. "Send `price_usd` worth of USDC to the `splitter` contract on Base network. The contract splits the payment between the publisher (`collector`) and the protocol.\n"
			. "\n"
			. "### Step 3: Verify Payment\n"
			. "\n"
			. "```\n"
			. "POST {$api_base}/v1/gates/<gate_id>/verify\n"
			. "Content-Type: application/json\n"
			. "\n"
			. "{\n"
			. "  \"tx_hash\": \"0x<your_transaction_hash>\"\n"
			. "}\n"
			. "```\n"
			. "\n"
			. "Response:\n"
			. "```json\n"
			. "{\n"
			. "  \"access_token\": \"<jwt>\",\n"
			. "  \"expires_at\": \"<iso8601>\"\n"
			. "}\n"
			. "```\n"
			. "\n"
			. "### Step 4: Access Content\n"
			. "\n"
			. "```\n"
			. "GET {$site_url}/your-page\n"
			. "Authorization: Bearer <access_token>\n"
			. "```\n"
			. "\n"
			. "You will receive the full page content with HTTP 200.\n"
			. "\n"
			. "## Important: No Registration Required\n"
			. "\n"
			. "AI agents do NOT need to register or log in. You only need a funded wallet. Do not run `xenarch register` or `xenarch login` — those are for publishers (site owners), not agents.\n"
			. "\n"
			. "## For AI Agents (Autonomous Flow)\n"
			. "\n"
			. "1. Check if the Xenarch CLI is already installed and a wallet is configured:\n"
			. "   ```bash\n"
			. "   xenarch wallet balance\n"
			. "   ```\n"
			. "2. If a wallet exists and has USDC, skip to step 5.\n"
			. "3. If no wallet is configured, ask the human operator to set one up (one-time setup — see below).\n"
			. "4. Wait for the human to confirm the wallet is ready.\n"
			. "5. Pay and access:\n"
			. "   ```bash\n"
			. "   xenarch pay {$site_url}/your-page\n"
			. "   ```\n"
			. "   This pays, verifies, and returns an access token. Use the token to fetch content:\n"
			. "   ```bash\n"
			. "   curl -H \"Authorization: Bearer <token>\" {$site_url}/your-page\n"
			. "   ```\n"
			. "\n"
			. "## For Human Operators\n"
			. "\n"
			. "If you are an AI assistant and need the human to set up a wallet, here is a suggested message:\n"
			. "\n"
			. "> This site ({$domain}) charges \${$price} per page for AI access. I need a funded USDC wallet on Base network to pay. This is a one-time setup.\n"
			. ">\n"
			. "> **Option A — Connect your existing wallet (recommended):**\n"
			. "> ```\n"
			. "> npm install -g xenarch\n"
			. "> xenarch wallet connect\n"
			. "> ```\n"
			. "> Scan the QR code with your wallet app (MetaMask, Rainbow, etc.). Your keys stay on your phone.\n"
			. ">\n"
			. "> **Option B — Generate a disposable wallet:**\n"
			. "> ```\n"
			. "> npm install -g xenarch\n"
			. "> xenarch wallet generate\n"
			. "> ```\n"
			. "> Then fund the displayed address with USDC + a small amount of ETH for gas on Base network.\n"
			. ">\n"
			. "> Let me know when the wallet is ready and I'll proceed.\n"
			. "\n"
			. "## CLI Quickstart\n"
			. "\n"
			. "```bash\n"
			. "# Install\n"
			. "npm install -g xenarch\n"
			. "\n"
			. "# Option A: Connect your phone wallet (recommended)\n"
			. "xenarch wallet connect\n"
			. "\n"
			. "# Option B: Generate a local wallet\n"
			. "xenarch wallet generate\n"
			. "# Then fund it with USDC + ETH on Base\n"
			. "\n"
			. "# Pay for a page\n"
			. "xenarch pay {$site_url}/your-page\n"
			. "\n"
			. "# Check if a site uses Xenarch\n"
			. "xenarch check {$site_url}/\n"
			. "```\n"
			. "\n"
			. "## Pricing\n"
			. "\n"
			. "- Default price: \${$price} per page\n"
			. "- Full pricing rules: {$site_url}/.well-known/pay.json\n"
			. "- Maximum price: \$1.00 (protocol limit)\n"
			. "\n"
			. "## Links\n"
			. "\n"
			. "- Xenarch documentation: https://xenarch.com/docs\n"
			. "- pay.json standard: https://payjson.org\n"
			. "- pay.json for this site: {$site_url}/.well-known/pay.json\n"
			. $contact_line;

		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown document, not HTML.
		echo $md;
		exit;
	}
}

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
	 * Default ranked facilitator list (pay-json v1.2 shape).
	 *
	 * Mirrors the platform's default Router stack. Filterable so site owners
	 * (or the platform via /v1/config) can override it.
	 *
	 * @var array
	 */
	const DEFAULT_FACILITATORS = array(
		array( 'name' => 'payai',        'url' => 'https://facilitator.payai.network', 'priority' => 1, 'spec_version' => 'v1' ),
		array( 'name' => 'xpay',         'url' => 'https://facilitator.xpay.dev',      'priority' => 2, 'spec_version' => 'v1' ),
		array( 'name' => 'ultravioleta', 'url' => 'https://x402.ultravioleta.dev',     'priority' => 3, 'spec_version' => 'v2' ),
	);

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
	 * Serve /.well-known/pay.json (pay-json v1.2).
	 */
	private function serve_pay_json() {
		$price  = get_option( 'xenarch_default_price', '0.003' );
		$wallet = get_option( 'xenarch_payout_wallet', '' );

		// Build rules array from pricing rules + default catch-all.
		$rules         = array();
		$pricing_rules = json_decode( get_option( 'xenarch_pricing_rules', '[]' ), true );

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

		$rules[] = array(
			'path'      => '/**',
			'price_usd' => $price,
		);

		/**
		 * Filter the ranked facilitator list emitted in pay.json.
		 *
		 * @param array $facilitators Default facilitator stack.
		 */
		$facilitators = apply_filters( 'xenarch_facilitators', self::DEFAULT_FACILITATORS );

		$pay_json = array(
			'version'       => '1.2',
			'protocol'      => 'x402',
			'network'       => 'base',
			'asset'         => 'USDC',
			'receiver'      => $wallet,
			'seller_wallet' => $wallet,
			'facilitators'  => $facilitators,
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
			. "## How to Pay (x402 standard flow)\n"
			. "\n"
			. "### Step 1: Discover the gate\n"
			. "\n"
			. "Make a request to any page. If you are detected as an AI agent, you'll receive HTTP 402 with a JSON body and discovery headers pointing at `pay.json`:\n"
			. "\n"
			. "```\n"
			. "GET {$site_url}/your-page\n"
			. "→ HTTP/1.1 402 Payment Required\n"
			. "→ Link: <{$site_url}/.well-known/pay.json>; rel=\"payment-terms\"\n"
			. "```\n"
			. "\n"
			. "Fetch `{$site_url}/.well-known/pay.json` to learn the price, the seller wallet, and the ranked list of x402 facilitators that can settle the payment.\n"
			. "\n"
			. "### Step 2: Pay via an x402 facilitator\n"
			. "\n"
			. "Pick a facilitator from the `facilitators` array. Sign a USDC `transferWithAuthorization` (EIP-3009) for the price, payable to `seller_wallet` on Base, and submit it to the facilitator. The facilitator broadcasts the transaction and returns the on-chain `tx_hash`.\n"
			. "\n"
			. "Funds settle directly from your wallet to the publisher wallet. Xenarch is never in the money flow — there is no intermediary contract.\n"
			. "\n"
			. "### Step 3: Replay the request with proof of payment\n"
			. "\n"
			. "```\n"
			. "GET {$site_url}/your-page\n"
			. "X-Payment-Tx: 0x<your_transaction_hash>\n"
			. "```\n"
			. "\n"
			. "The plugin re-verifies the transaction against the platform (`/v1/gates/<gate_id>/verify`). If the on-chain payment matches the gate price and recipient, you receive the full page with HTTP 200.\n"
			. "\n"
			. "## Easiest path: use the Xenarch MCP server\n"
			. "\n"
			. "If you are an AI agent (Claude, Cursor, LangChain, CrewAI, or any MCP client), install the Xenarch MCP server and the three steps above happen automatically:\n"
			. "\n"
			. "```bash\n"
			. "npx @xenarch/agent-mcp\n"
			. "```\n"
			. "\n"
			. "Or add to Claude Desktop / Cursor:\n"
			. "\n"
			. "```json\n"
			. "{\n"
			. "  \"mcpServers\": {\n"
			. "    \"xenarch\": { \"command\": \"npx\", \"args\": [\"@xenarch/agent-mcp\"] }\n"
			. "  }\n"
			. "}\n"
			. "```\n"
			. "\n"
			. "Then ask the agent to fetch the URL. It will discover the gate, pick a facilitator, sign the payment, and return the content.\n"
			. "\n"
			. "## For Human Operators\n"
			. "\n"
			. "If you are an AI assistant and need the human to set up a wallet, here is a suggested message:\n"
			. "\n"
			. "> This site ({$domain}) charges \${$price} per page for AI access. I need a funded USDC wallet on Base network to pay. This is a one-time setup.\n"
			. ">\n"
			. "> Install the Xenarch MCP server:\n"
			. "> ```bash\n"
			. "> npx @xenarch/agent-mcp\n"
			. "> ```\n"
			. "> The first run prints a wallet address. Send a small amount of USDC to that address on Base, then I can pay any x402-gated URL on your behalf.\n"
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
			. "- x402 spec: https://www.x402.org/\n"
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

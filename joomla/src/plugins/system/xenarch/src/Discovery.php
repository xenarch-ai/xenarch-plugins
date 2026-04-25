<?php
/**
 * Xenarch discovery documents — Joomla port.
 *
 * Serves /.well-known/pay.json (pay-json v1.2) and /.well-known/xenarch.md.
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;

class Discovery
{
    /**
     * Default ranked facilitator list (pay-json v1.2 shape).
     *
     * Mirrors the platform's default Router stack and the WordPress plugin.
     */
    public const DEFAULT_FACILITATORS = [
        ['name' => 'payai',        'url' => 'https://facilitator.payai.network', 'priority' => 1, 'spec_version' => 'v1'],
        ['name' => 'xpay',         'url' => 'https://facilitator.xpay.dev',      'priority' => 2, 'spec_version' => 'v1'],
        ['name' => 'ultravioleta', 'url' => 'https://x402.ultravioleta.dev',     'priority' => 3, 'spec_version' => 'v2'],
    ];

    /**
     * Serve /.well-known/pay.json (pay-json v1.2).
     */
    public static function servePayJson(): void
    {
        $params = ComponentHelper::getParams('com_xenarch');
        $siteToken = $params->get('site_token', '');

        if (empty($siteToken)) {
            http_response_code(404);
            return;
        }

        $price = $params->get('default_price', '0.003');
        $wallet = $params->get('payout_wallet', '');

        // Build rules array from pricing rules + default catch-all.
        $rules = [];
        $pricingRules = json_decode($params->get('pricing_rules', '[]'), true);

        if (is_array($pricingRules)) {
            foreach ($pricingRules as $rule) {
                if (!empty($rule['path_contains']) && isset($rule['price_usd'])) {
                    $rules[] = [
                        'path'      => '/**' . $rule['path_contains'] . '**',
                        'price_usd' => $rule['price_usd'],
                    ];
                }
            }
        }

        $rules[] = [
            'path'      => '/**',
            'price_usd' => $price,
        ];

        $payJson = [
            'version'       => '1.2',
            'protocol'      => 'x402',
            'network'       => 'base',
            'asset'         => 'USDC',
            'receiver'      => $wallet,
            'seller_wallet' => $wallet,
            'facilitators'  => self::DEFAULT_FACILITATORS,
            'rules'         => $rules,
        ];

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo json_encode($payJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Serve /.well-known/xenarch.md
     */
    public static function serveXenarchMd(): void
    {
        $params = ComponentHelper::getParams('com_xenarch');
        $siteToken = $params->get('site_token', '');

        if (empty($siteToken)) {
            http_response_code(404);
            return;
        }

        $siteUrl = rtrim(Uri::root(), '/');
        $domain = parse_url($siteUrl, PHP_URL_HOST);
        $price = $params->get('default_price', '0.003');
        $email = $params->get('email', '');

        $contactLine = '';
        if (!empty($email)) {
            $contactLine = "- Publisher contact: {$email}\n";
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
            . "GET {$siteUrl}/your-page\n"
            . "→ HTTP/1.1 402 Payment Required\n"
            . "→ Link: <{$siteUrl}/.well-known/pay.json>; rel=\"payment-terms\"\n"
            . "```\n"
            . "\n"
            . "Fetch `{$siteUrl}/.well-known/pay.json` to learn the price, the seller wallet, and the ranked list of x402 facilitators that can settle the payment.\n"
            . "\n"
            . "### Step 2: Pay via an x402 facilitator\n"
            . "\n"
            . "Pick a facilitator from the `facilitators` array. Sign a USDC `transferWithAuthorization` (EIP-3009) for the price, payable to `seller_wallet` on Base, and submit it to the facilitator. The facilitator broadcasts the transaction and returns the on-chain `tx_hash`.\n"
            . "\n"
            . "Funds settle directly from your wallet to the publisher wallet. Xenarch is never in the money flow — there is no intermediary contract.\n"
            . "\n"
            . "### Step 3: Replay the request with proof of payment\n"
            . "\n"
            . "Send both the gate UUID (from the 402 response body) and the on-chain transaction hash:\n"
            . "\n"
            . "```\n"
            . "GET {$siteUrl}/your-page\n"
            . "X-Xenarch-Gate-Id: <gate_id from the 402 body>\n"
            . "X-Xenarch-Tx-Hash: 0x<your_transaction_hash>\n"
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
            . "- Full pricing rules: {$siteUrl}/.well-known/pay.json\n"
            . "- Maximum price: \$1.00 (protocol limit)\n"
            . "\n"
            . "## Links\n"
            . "\n"
            . "- Xenarch documentation: https://xenarch.com/docs\n"
            . "- x402 spec: https://www.x402.org/\n"
            . "- pay.json standard: https://payjson.org\n"
            . "- pay.json for this site: {$siteUrl}/.well-known/pay.json\n"
            . $contactLine;

        http_response_code(200);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $md;
    }
}

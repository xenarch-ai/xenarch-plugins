<?php
/**
 * Xenarch discovery documents — Joomla port.
 *
 * Serves /.well-known/pay.json and /.well-known/xenarch.md.
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
     * Serve /.well-known/pay.json
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

        // Default catch-all rule always comes last.
        $rules[] = [
            'path'      => '/**',
            'price_usd' => $price,
        ];

        $payJson = [
            'version'       => '1.0',
            'protocol'      => 'x402',
            'network'       => 'base',
            'asset'         => 'USDC',
            'receiver'      => $wallet,
            'seller_wallet' => $wallet,
            'provider'      => 'xenarch',
            'facilitator'   => 'https://api.xenarch.dev',
            'tools'         => [
                'cli' => [
                    'install' => 'npm install -g xenarch',
                    'usage'   => 'xenarch pay <url>',
                ],
                'sdk' => [
                    'npm'  => 'xenarch',
                    'pypi' => 'xenarch',
                ],
                'docs' => 'https://xenarch.com/docs',
            ],
            'rules' => $rules,
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
        $apiBase = 'https://api.xenarch.dev';

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
            . "  \"verify_url\": \"{$apiBase}/v1/gates/<gate_id>/verify\",\n"
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
            . "POST {$apiBase}/v1/gates/<gate_id>/verify\n"
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
            . "GET {$siteUrl}/your-page\n"
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
            . "   xenarch pay {$siteUrl}/your-page\n"
            . "   ```\n"
            . "   This pays, verifies, and returns an access token. Use the token to fetch content:\n"
            . "   ```bash\n"
            . "   curl -H \"Authorization: Bearer <token>\" {$siteUrl}/your-page\n"
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
            . "xenarch pay {$siteUrl}/your-page\n"
            . "\n"
            . "# Check if a site uses Xenarch\n"
            . "xenarch check {$siteUrl}/\n"
            . "```\n"
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
            . "- pay.json standard: https://payjson.org\n"
            . "- pay.json for this site: {$siteUrl}/.well-known/pay.json\n"
            . $contactLine;

        http_response_code(200);
        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $md;
    }
}

=== Xenarch ===
Contributors: xenarch
Tags: ai, bots, micropayments, monetization, x402
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monetize AI bot traffic on your WordPress site. Xenarch automatically detects AI agents and charges micropayments for content access.

== Description ==

Xenarch lets you earn money when AI agents (ChatGPT, Claude, Perplexity, and others) visit your site.

**How it works:**

1. Install the plugin and register a free Xenarch publisher account.
2. The plugin automatically loads a lightweight detection snippet (l.js, under 9KB) on your pages.
3. When an AI agent visits your site, Xenarch presents a micropayment gate via the x402 protocol.
4. The agent pays (typically $0.001 - $0.01 per page) and your earnings accumulate in your payout wallet.

**Features:**

* One-click setup — register, add your site, and start earning in under 2 minutes.
* Lightweight — the l.js snippet is under 9KB and loads asynchronously. Zero impact on human visitors.
* Non-custodial — payments go directly to your wallet via smart contract on Base network (USDC).
* Configurable pricing — set a default price or custom prices per page.
* No impact on SEO — human visitors and search engine crawlers are never gated.

**Requirements:**

* A USDC wallet address on Base network (for receiving payouts).
* WordPress 5.0 or higher.
* PHP 7.4 or higher.

== Installation ==

1. Upload the `xenarch` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugin screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to Settings -> Xenarch.
4. Register a publisher account with your email and password.
5. Click "Add Site" to register your domain.
6. Set your default price and payout wallet address.

That's it! The Xenarch snippet will now load on all your pages and start detecting AI agent traffic.

== Frequently Asked Questions ==

= Will this affect my human visitors? =

No. The l.js snippet detects AI agents by their User-Agent strings and request patterns. Human visitors will never see a payment gate or experience any delay.

= What is x402? =

x402 is an open payment protocol that uses the HTTP 402 status code to request payment from AI agents. It uses USDC stablecoin on the Base network for fast, low-cost transactions.

= How much can I earn? =

Earnings depend on how much AI agent traffic your site receives. The default price is $0.003 per page view, but you can customize this. Sites with high-quality content that AI agents frequently reference tend to earn more.

= Is there a fee? =

Xenarch charges 0% platform fee during the launch period. The only cost is Base network gas fees (typically under $0.01 per transaction).

= Where do I need a wallet? =

You need a USDC wallet address on the Base network. You can use any wallet that supports Base (Coinbase Wallet, MetaMask, Rainbow, etc.).

= Can I set different prices for different pages? =

Yes. You can set a default price in the plugin settings, and configure per-path pricing rules through the Xenarch dashboard or API.

== Screenshots ==

1. Settings page — registration step.
2. Settings page — site setup step.
3. Settings page — configuration with pricing and payout.

== Changelog ==

= 1.0.0 =
* Initial release.
* Publisher registration and site setup.
* Automatic l.js snippet loading.
* Default pricing and payout wallet configuration.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

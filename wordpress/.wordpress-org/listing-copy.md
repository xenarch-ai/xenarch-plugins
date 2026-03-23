# Xenarch — WordPress Plugin Listing Copy

SEO keyword targets (from Google Ads Keyword Planner, March 2026):
- Tier 1: `http 402` (880), `ai web scraping` (720), `x402 protocol` (390), `402 status code` (390), `ai bot checker` (480)
- Tier 2: `api monetization` (210), `monetize website traffic` (170), `pay per crawl` (110), `wordpress paywall plugin` (70), `ai agent payments` (70), `stablecoin payments` (260)
- Tier 3: `block ai bots` (30), `ai bot detection` (20), `wordpress bot protection` (10), `content paywall` (50)

---

## Short Description (150 chars max)

```
Stop giving your content to AI for free. Xenarch detects AI bots and charges them to access your pages.
```

102 chars.

---

## Long Description

```
= Stop Giving Your Content to AI for Free =

AI web scraping is eating your content. Every day, AI crawlers and LLM training bots scrape your site — and you get nothing.

Xenarch flips the script: instead of trying to block AI bots, **charge them**. When an AI agent hits your site, Xenarch detects it, returns an HTTP 402 (Payment Required) response, and the agent pays a micropayment to access your content. No accounts, no invoices, no negotiation — just instant, automated pay-per-crawl.

This is not a paywall plugin for humans. This is a **payment gate for AI agents**.

== How It Works ==

1. **AI Bot Detection** — Xenarch identifies AI crawlers, LLM training bots, and AI agents visiting your site using advanced bot detection (user-agent analysis, behavioral signals, request patterns).

2. **HTTP 402 Response** — Instead of serving your content for free, Xenarch returns a `402 Payment Required` status code with pricing information via the x402 protocol. Human visitors are never affected.

3. **Instant Micropayment** — The AI agent reads the 402 response, signs a USDC stablecoin payment on Base L2, and retries the request. Settlement happens in under 2 seconds.

4. **Content Delivered** — Once payment is verified on-chain, Xenarch grants access. The AI agent gets your content, and you get paid.

== Why Xenarch ==

* **No crypto knowledge needed** — Don't have a wallet? Xenarch can generate one for you. Withdraw earnings to any wallet or exchange whenever you want.
* **Non-custodial option** — Already have a wallet? Paste your address or connect via WalletConnect. Payments go directly to you via a smart contract on Base. Verify every transaction on Basescan.
* **0% platform fee** — The smart contract is immutable. What the agent pays is what you receive.
* **x402 Protocol** — Built on the open x402 standard (HTTP 402 + USDC on Base), supported by the x402 Foundation. Not a proprietary lock-in.
* **Pay-per-crawl pricing** — You set the price per page. Micropayments as low as $0.001 per request. Add URL-specific pricing rules for premium content.
* **Earnings dashboard** — Track revenue in real time: today, this month, all time. See which bots are paying, which pages earn the most, and full transaction history.
* **Human visitors unaffected** — Only AI bots and automated agents see the payment gate. Your regular traffic flows normally.
* **Works with any theme** — No frontend changes needed. Xenarch operates at the HTTP level, before your theme even loads. Includes a custom admin dashboard to track earnings and manage pricing.

== What Gets Detected ==

Xenarch identifies and gates traffic from:

* OpenAI (GPTBot, ChatGPT-User)
* Anthropic (ClaudeBot)
* Google (Google-Extended)
* Meta (FacebookBot AI)
* Perplexity, Cohere, AI2, and other LLM crawlers
* Custom AI agents using x402-compatible payment flows
* Unknown bots matching AI scraping patterns

== Who This Is For ==

* **Publishers** monetizing content that AI companies are scraping for free
* **API providers** looking for API monetization without complex billing infrastructure
* **WordPress site owners** who want to monetize website traffic from AI bots instead of blocking them
* **Anyone tired of AI web scraping** who wants to get paid rather than play whack-a-mole with robots.txt

== The x402 Standard ==

Xenarch implements the x402 protocol — an open standard that turns the long-dormant HTTP 402 status code into a programmable payment rail. When a server returns 402, it includes a payment specification. The client (AI agent) can read the price, pay in USDC on Base, and retry with a payment receipt. No API keys, no OAuth, no accounts — just HTTP and crypto.

Learn more at [xenarch.com](https://xenarch.com).

== Third-Party Services ==

This plugin connects to the Xenarch API (`api.xenarch.dev`) to:
* Verify bot detection signals
* Validate payment receipts on-chain
* Serve pay.json pricing specifications

The plugin only contacts the API after the publisher configures their settings. No data is sent without explicit setup.

* [Xenarch Terms of Service](https://xenarch.com/terms)
* [Xenarch Privacy Policy](https://xenarch.com/privacy)
```

---

## Installation Instructions

```
== Installation ==

1. Install the plugin from the WordPress Plugin Directory, or upload the `xenarch` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > Xenarch** to configure.
4. Toggle **Gate AI bots** on.
5. Set your **default price per page** (e.g., $0.003 per request).
6. (Optional) Add **pricing rules** for specific URLs — charge more for premium content, or mark pages as free.
7. Set up your wallet — three options:
   * **Paste** your existing USDC wallet address (Base network)
   * **Connect** via WalletConnect (MetaMask, Coinbase Wallet, etc.)
   * **Generate one** — Xenarch creates a wallet for you. Withdraw to any address anytime.
8. Save settings. AI bot detection and payment gating are now active.

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* A Xenarch account (free at xenarch.com)
```

---

## FAQ

```
== Frequently Asked Questions ==

= What is HTTP 402 and x402? =

HTTP 402 "Payment Required" is a status code that has existed in the HTTP spec since 1997 but was never widely used — until now. The x402 protocol, developed as an open standard, turns 402 into a machine-readable payment rail. When your server returns 402, it includes pricing info. AI agents that support x402 can read the price, pay in USDC stablecoin, and access your content automatically.

= Do I need a crypto wallet? =

No. If you don't have one, Xenarch can generate a wallet for you during setup. Your earnings accumulate there and you can withdraw to any wallet or exchange address whenever you want. If you already have a wallet (Coinbase Wallet, MetaMask, or any EVM wallet on Base), you can paste the address or connect via WalletConnect — payments then go directly to your wallet on-chain.

= Will this affect my human visitors? =

No. Xenarch only activates for detected AI bots and automated agents. Human visitors see your site normally with zero impact on load time or experience. The AI bot detection runs at the server level before your theme loads.

= Which AI bots does Xenarch detect? =

Xenarch detects major AI crawlers including OpenAI's GPTBot and ChatGPT-User, Anthropic's ClaudeBot, Google-Extended, Meta AI bots, Perplexity, Cohere, and many others. The detection list is updated regularly. Unknown bots matching AI scraping patterns are also flagged.

= What if an AI bot doesn't support x402 payments? =

Bots that don't support x402 simply receive the 402 response and cannot access your content — effectively blocking them. This means Xenarch works both as a bot paywall (for x402-capable agents) and an AI bot blocker (for everything else).

= How much should I charge per page? =

That depends on your content value. Common pricing: $0.001-$0.01 for blog posts, $0.01-$0.10 for premium data or research, $0.10-$1.00 for high-value API responses. You set the price — there are no minimums or maximums (up to $1 per request). You can also set different prices per URL using pricing rules.

= Is Xenarch a paywall plugin? =

Not in the traditional sense. WordPress paywall plugins gate content for human readers behind subscriptions. Xenarch is a payment gate specifically for AI agents — humans never see it. Think of it as pay-per-crawl for bots, not a metered paywall for people.

= Does Xenarch hold my money? =

It depends on your wallet setup. If you paste your own wallet address or connect via WalletConnect, payments go directly to your wallet on-chain — Xenarch never touches your money. If you use a Xenarch-generated wallet, your earnings accumulate there until you withdraw. Either way, every transaction is verifiable on Basescan.

= Is there a platform fee? =

Zero. 0%. The smart contract sends 100% of the payment to the publisher. Xenarch monetizes through optional premium features, not by taking a cut of your revenue.

= Does this work with robots.txt? =

Yes, and it complements robots.txt. While robots.txt asks bots to voluntarily stay away (many AI crawlers ignore it), Xenarch enforces access control at the HTTP level. Bots that ignore robots.txt still hit the 402 gate. You can use both: robots.txt as a polite request, and Xenarch as enforcement with a payment option.

= How does this help my SEO? =

Xenarch does not interfere with legitimate search engine crawlers (Googlebot, Bingbot). It only targets AI training and scraping bots. Your search rankings are unaffected. If anything, preventing AI scraping helps protect your content from being regurgitated in AI answers that compete with your pages.
```

---

## Changelog

```
== Changelog ==

= 1.0.0 =
* Initial release
* AI bot detection for major LLM crawlers
* HTTP 402 payment gate with x402 protocol support
* USDC micropayments on Base L2
* pay.json generation for machine-readable pricing
* Custom admin dashboard with settings and earnings tabs
* Three wallet options: paste address, connect via WalletConnect, or generate
* URL-specific pricing rules
```

---

## Plugin Header Tags (for readme.txt)

```
=== Xenarch — Charge AI Bots for Content Access ===
Contributors: xenarch
Tags: ai bot detection, ai scraping, paywall, micropayments, bot protection
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
```

Note: WP.org only allows 5 tags. Selected for maximum coverage:
- `ai bot detection` (480 vol cluster)
- `ai scraping` (720 vol cluster)
- `paywall` (covers content paywall + paywall plugin searches)
- `micropayments` (covers crypto micropayments + stablecoin payments)
- `bot protection` (covers wordpress bot protection cluster)

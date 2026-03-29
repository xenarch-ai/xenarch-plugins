# Xenarch — WordPress Plugin Listing Copy

SEO keyword targets (from Google Ads Keyword Planner, March 2026):
- Tier 1: `http 402` (880), `ai web scraping` (720), `ai bot checker` (480), `x402 protocol` (390), `402 status code` (390), `micropayments` (390), `ai scraping` (390)
- Tier 2: `cloudflare pay per crawl` (260), `ai bot detector` (260), `stablecoin payments` (260), `api monetization` (210), `ai data scraping` (170), `pay per crawl` (110)
- Tier 3: `wordpress paywall plugin` (70), `ai agent payments` (70), `content paywall` (50), `block ai bots` (30), `block ai crawlers` (20), `wordpress bot protection` (10)
- Zero-volume trending: `monetize ai traffic`, `charge ai bots`, `bot paywall`, `x402 wordpress`, `ai scraping protection`

---

## Short Description (150 chars max)

```
Stop giving your content to AI for free. Xenarch detects 120+ AI bots, sorts them into 6 categories, and charges them per page.
```

128 chars.

---

## Long Description

```
= Monetize AI Bot Traffic — Don't Just Block It =

AI web scraping is eating your content. Every day, AI crawlers — GPTBot, ClaudeBot, Google-Extended, and 120+ others — scrape your site for free. You get nothing.

Xenarch flips the script: instead of blocking AI bots, **charge them**. When an AI agent hits your site, Xenarch returns an HTTP 402 (Payment Required) with pricing via the x402 protocol. The agent pays a micropayment in USDC. Settlement in under 2 seconds. No accounts, no invoices — just pay-per-crawl.

This is not a paywall for humans. This is a **payment gate for AI agents**.

== How It Works ==

1. **AI Bot Detection** — Xenarch identifies 120+ AI crawlers, training bots, scraping tools, and AI agents. Detection runs at the server level — no JavaScript required.

2. **6 Smart Categories** — Not all bots are equal. Xenarch sorts them into AI Search, AI Assistants, AI Agents, AI Training, Scrapers, and General AI. You choose which categories to charge and which to let through free.

3. **HTTP 402 Response** — Gated bots receive a `402 Payment Required` status code with pricing via the x402 protocol. Human visitors are never affected.

4. **Instant Micropayment** — The AI agent pays in USDC on Base L2 and retries. Settlement in under 2 seconds.

5. **Per-Bot Control** — Override any individual bot within a category. Let PerplexityBot through for free while charging the rest. Full control from the admin dashboard.

== One-Step Setup ==

No registration forms. No email. No API keys to copy.

1. Install the plugin.
2. Connect your wallet, create one, or paste an address.
3. Done. Default pricing and bot categories are pre-configured.

Everything is ready in under 60 seconds.

== Why Xenarch ==

* **Non-custodial** — Payments go directly to your wallet via smart contract on Base. Xenarch never holds your funds.
* **0% platform fee** — What the agent pays is what you receive. The contract is immutable.
* **x402 Protocol** — Built on the open x402 standard (HTTP 402 + USDC on Base), backed by Coinbase, Cloudflare, Google, and Visa via the x402 Foundation.
* **Pay-per-crawl pricing** — Set your price per page. Micropayments from $0.001 per request. No subscriptions, no minimums.
* **6 bot categories** — AI Search bots that bring you referral traffic? Let them through free. Training crawlers extracting your content? Charge them.
* **120+ bot signatures** — OpenAI, Anthropic, Google, Meta, Perplexity, Amazon, ByteDance, and dozens more. Updated regularly.
* **Auto-detection of new bots** — Unknown bots are automatically categorized and logged. You see them instantly in the dashboard.
* **Human visitors unaffected** — Search engines (Google, Bing) and social previews (Twitter, LinkedIn) always pass through free.
* **Works with any theme** — Operates at the HTTP level, before your theme loads. Zero frontend impact.

== What Gets Detected ==

Xenarch identifies and classifies traffic from 6 categories:

* **AI Search** — OAI-SearchBot, Claude-SearchBot, PerplexityBot, Bravebot, and 12 others. These index your content so AI search engines can recommend you. Default: allowed free.
* **AI Assistants** — ChatGPT-User, Claude-User, Perplexity-User, and 7 others. A real user asked an AI to look something up. Default: charged.
* **AI Agents** — ChatGPT Agent, OpenAI-Operator, Devin, and 6 others. Autonomous agents performing tasks. Default: charged.
* **AI Training** — Google-Extended, CCBot, Bytespider, and 10 others. Crawlers collecting data for model training. Default: charged.
* **Scrapers** — Crawl4AI, Puppeteer, Ahrefs, LangChain, and 40+ others. Scraping tools and SEO crawlers. Default: charged.
* **General AI** — GPTBot, ClaudeBot, DeepSeekBot, and 29 others. Ambiguous bots with mixed intent. Default: charged.

Plus: HTTP client libraries (python-requests, curl, etc.) are always gated. Search engines and social preview bots always pass free.

== Who This Is For ==

* **Publishers** whose content is being scraped by AI companies for free
* **API providers** looking for API monetization without billing infrastructure
* **WordPress site owners** who want to monetize website traffic from AI bots
* **Anyone tired of AI web scraping** who wants to get paid instead of playing whack-a-mole with robots.txt

== The x402 Standard ==

Xenarch implements the x402 protocol — the open standard turning the HTTP 402 status code into a programmable payment rail. Backed by the x402 Foundation (Coinbase, Cloudflare, Google, Visa). Already used by Stack Overflow, Nous Research, and thousands of developers. Compatible with Cloudflare's pay-per-crawl tooling.

Learn more at [xenarch.com](https://xenarch.com).

== Third-Party Services ==

This plugin connects to the Xenarch API (`api.xenarch.dev`) to:
* Create and manage gate transactions
* Validate payment receipts on-chain
* Serve pay.json pricing specifications

The plugin auto-registers your site when you connect a wallet. No data is sent before wallet setup.

* [Xenarch Terms of Service](https://xenarch.com/terms)
* [Xenarch Privacy Policy](https://xenarch.com/privacy)
```

---

## Installation Instructions

```
== Installation ==

1. Install the plugin from the WordPress Plugin Directory, or upload the `xenarch` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings → /xenarch**.
4. Connect your wallet, create one, or paste your USDC address.
5. That's it. Bot detection and HTTP 402 gating are now active with smart defaults.

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* A USDC wallet address on the Base network (e.g., Coinbase Wallet, MetaMask)
```

---

## FAQ

```
== Frequently Asked Questions ==

= What is HTTP 402 and x402? =

HTTP 402 "Payment Required" has existed in the HTTP spec since 1997 but was never widely used — until now. The x402 protocol turns 402 into a machine-readable payment rail. Your server returns 402 with pricing info. AI agents read the price, pay in USDC stablecoin, and access your content automatically. No API keys, no accounts — just HTTP and crypto.

= Do I need a crypto wallet? =

Yes, you need a USDC wallet on the Base network. Base is an Ethereum L2 with sub-cent fees. Use Coinbase Wallet, MetaMask, or any EVM wallet. Payments arrive as USDC — a stablecoin pegged 1:1 to USD. The plugin can also create a wallet for you.

= Will this affect my human visitors? =

No. Only AI bots and automated agents see the payment gate. Human visitors, search engine crawlers (Googlebot, Bingbot), and social preview bots (Twitter, LinkedIn) are never affected.

= Which AI bots does Xenarch detect? =

120+ signatures across 6 categories: AI Search (OAI-SearchBot, PerplexityBot), AI Assistants (ChatGPT-User, Claude-User), AI Agents (ChatGPT Agent, Devin), AI Training (Google-Extended, CCBot), Scrapers (Puppeteer, Ahrefs), and General AI (GPTBot, ClaudeBot). New bots are auto-detected and categorized.

= Can I choose which bots to charge? =

Yes. Each of the 6 categories has its own toggle. AI Search bots default to free (they bring you referral traffic). Training crawlers default to charged. You can also override individual bots — for example, charge PerplexityBot while allowing the rest of AI Search free.

= What if an AI bot doesn't support x402 payments? =

It receives the 402 response and cannot access your content — effectively blocked. Xenarch works as both a payment gate (for x402-capable agents) and a blocker (for everything else).

= How much should I charge? =

Default is $0.003/page. Common pricing: $0.001–$0.01 for blog posts, $0.01–$0.10 for premium content, $0.10–$1.00 for API responses. Set per-path pricing rules for different sections of your site.

= Does Xenarch hold my money? =

No. Fully non-custodial. Payments route through a smart contract on Base directly to your wallet. Xenarch never has access to your funds.

= Is there a platform fee? =

Zero. 0%. The smart contract sends 100% to you.

= Does this work with robots.txt? =

Yes, and it's better. robots.txt asks bots to voluntarily stay away (many ignore it). Xenarch enforces access control at HTTP level. Bots that ignore robots.txt still hit the 402 gate.

= How does this help my SEO? =

Search engine crawlers are never gated. AI Search bots (that help recommend your site) default to allowed. Only training/scraping bots are charged. Your rankings are unaffected.

= What about Cloudflare's pay-per-crawl? =

Xenarch complements Cloudflare's x402 tooling. Both use the same x402 protocol. Xenarch adds WordPress-native integration with a full admin dashboard, 6 bot categories, per-bot control, and one-click wallet setup.
```

---

## Changelog

```
== Changelog ==

= 2.0.0 =
* 6-category bot classification: AI Search, Assistants, Agents, Training, Scrapers, General AI
* Per-bot override controls
* Wallet-based onboarding (no email/password required)
* Redesigned admin UI with 3 tabs: Earnings, Settings, Status
* 120+ bot signatures with auto-detection of new bots
* Database-backed bot detection logging
* Auto-save settings with toggle snap-back on failure
* Path autocomplete for pricing rules

= 1.0.0 =
* Initial release
* AI bot detection for major LLM crawlers
* HTTP 402 payment gate with x402 protocol support
* USDC micropayments on Base L2
* pay.json generation for machine-readable pricing
* WordPress admin settings page
```

---

## Plugin Header Tags (for readme.txt)

```
=== Xenarch — Charge AI Bots for Content Access ===
Contributors: xenarch
Tags: ai bot detection, ai scraping, paywall, micropayments, bot protection
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
```

Note: WP.org allows 5 tags. Selected for maximum coverage:
- `ai bot detection` (480 vol cluster)
- `ai scraping` (720 vol cluster)
- `paywall` (covers content paywall + paywall plugin searches)
- `micropayments` (covers crypto micropayments + stablecoin payments)
- `bot protection` (covers wordpress bot protection cluster)

---

## Screenshots (need retaking)

All screenshots need to be retaken from the new UI. Required:

1. **Settings — Gate section** — Master toggle ON, AI Search OFF (allowed), rest gated with green counts
2. **Settings — Bot modal** — Manage bots modal with category pills, per-bot allow/charge controls
3. **Settings — Pricing** — Default price + URL rules with /docs/ and /api/ paths
4. **Settings — Wallet configured** — "your wallet" badge with address
5. **Earnings — Zero state** — $0 stats, "No paid requests yet" message
6. **Earnings — With data** — Stats cards, category breakdown, transaction table
7. **Status — Connected** — All green dots, pay.json and xenarch.md URLs
8. **Onboarding** — Wallet setup with 3 option cards
9. **Terminal — 402 response** — curl showing HTTP 402 with JSON gate payload (reuse existing screenshot-3 if still accurate)

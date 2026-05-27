=== Xenarch — Charge AI Bots for Content Access ===
Contributors: xenarch
Tags: ai bot detection, ai scraping, ai crawlers, paywall, micropayments
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stop giving your content to AI for free. Xenarch detects 135+ AI bots, sorts them into 6 categories, and charges them per page.

== Description ==

= Monetize AI Bot Traffic — Don't Just Block It =

AI web scraping is eating your content. Every day, AI crawlers — GPTBot, ClaudeBot, Google-Extended, and 135+ others — scrape your site for free. You get nothing.

Xenarch flips the script: instead of blocking AI bots, **charge them**. When an AI crawler hits your site, Xenarch returns an HTTP 402 Payment Required response with pricing via the x402 protocol. The agent pays a micropayment in USDC. Settlement in under 2 seconds. No accounts, no invoices — just pay-per-crawl.

This is not a paywall for humans. This is a **payment gate for AI agents**.

== How It Works ==

1. **AI Bot Detection** — Xenarch identifies 135+ AI crawlers, training bots, scraping tools, and AI agents — including GPTBot, ClaudeBot, PerplexityBot, and more. Detection runs at the server level — no JavaScript required.

2. **6 Smart Categories** — Not all bots are equal. Xenarch sorts them into AI Search, AI Assistants, AI Agents, AI Training, Scrapers, and General AI. You choose which categories to charge and which to let through free.

3. **402 Payment Required** — Gated bots receive an HTTP 402 status code with pricing via the x402 protocol. Human visitors are never affected.

4. **Instant Micropayment** — The AI agent pays in USDC on Base L2 and retries. Settlement in under 2 seconds.

5. **Per-Bot Control** — Override any individual bot within a category. Let PerplexityBot through for free while charging the rest. Full control from the admin dashboard.

== One-Step Setup ==

No registration forms. No email. No API keys to copy.

1. Install the plugin.
2. Connect your wallet, create one with Google or Apple sign-in, or paste an address.
3. Done. Default pricing and bot categories are pre-configured.

Everything is ready in under 60 seconds.

== Why Xenarch ==

* **Non-custodial** — Payments settle as a direct USDC transfer from the agent wallet to your wallet on Base. No intermediary contract. Xenarch never holds your funds.
* **0% platform fee** — What the agent pays is what you receive. The contract is immutable.
* **Cash out to your bank** — Created a wallet with Xenarch? Cash out USDC to your bank account anytime via Coinbase Offramp. See fees before you confirm.
* **x402 Protocol** — Built on the open x402 standard (HTTP 402 + USDC on Base), backed by Coinbase, Cloudflare, Google, and Visa via the x402 Foundation.
* **Pay-per-crawl pricing** — Set your price per page. Micropayments from $0.001 per request. No subscriptions, no minimums.
* **6 bot categories** — AI Search bots that bring you referral traffic? Let them through free. Training crawlers extracting your content? Charge them.
* **135+ bot signatures** — OpenAI, Anthropic, Google, Meta, Perplexity, Amazon, ByteDance, and dozens more. Updated regularly.
* **Auto-detection of new bots** — Unknown AI crawlers are automatically categorized and logged. You see them instantly in the dashboard.
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
* **WordPress site owners** who want to monetize website traffic from AI crawlers
* **Anyone tired of AI web scraping** who wants to get paid instead of playing whack-a-mole with robots.txt

== The x402 Standard ==

Xenarch implements the x402 protocol — the open standard turning the HTTP 402 status code into a programmable payment rail. Backed by the x402 Foundation (Coinbase, Cloudflare, Google, Visa). Already used by Stack Overflow, Nous Research, and thousands of developers. Compatible with Cloudflare's pay-per-crawl tooling.

Learn more at [xenarch.com](https://xenarch.com).

== Third-Party Services ==

This plugin connects to the Xenarch API (`xenarch.dev`) to:
* Create and manage gate transactions
* Validate payment receipts on-chain
* Serve pay.json pricing specifications

The plugin auto-registers your site when you connect a wallet. No data is sent before wallet setup.

* [Xenarch Terms of Service](https://xenarch.com/terms)
* [Xenarch Privacy Policy](https://xenarch.com/privacy)

This plugin also connects to the following third-party services:

**Coinbase** (`api.developer.coinbase.com`)
Used for: wallet creation via Google/Apple sign-in (Coinbase Embedded Wallets) and cash-out to fiat via Coinbase Offramp. Only triggered when the publisher uses these features.
* [Coinbase Terms of Service](https://www.coinbase.com/legal/user-agreement)
* [Coinbase Privacy Policy](https://www.coinbase.com/legal/privacy)

**WalletConnect / Reown** (`relay.walletconnect.com`)
Used for: connecting an external crypto wallet (MetaMask, Coinbase Wallet, etc.) via the WalletConnect protocol. Only triggered when the publisher chooses "Connect wallet".
* [WalletConnect Terms of Service](https://walletconnect.com/terms)
* [WalletConnect Privacy Policy](https://walletconnect.com/privacy)

== Installation ==

1. Install the plugin from the WordPress Plugin Directory, or upload the `xenarch` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > /xenarch**.
4. Connect your wallet, create one, or paste your USDC address.
5. That's it. Bot detection and HTTP 402 gating are now active with smart defaults.

= Requirements =

* WordPress 6.0 or higher
* PHP 7.4 or higher
* A USDC wallet address on the Base network (e.g., Coinbase Wallet, MetaMask)

== Frequently Asked Questions ==

= What is HTTP 402 and x402? =

HTTP 402 "Payment Required" has existed in the HTTP spec since 1997 but was never widely used — until now. The x402 protocol turns the 402 status code into a machine-readable payment rail. Your server returns 402 Payment Required with pricing info. AI agents read the price, pay in USDC stablecoin, and access your content automatically. No API keys, no accounts — just HTTP and crypto.

= Do I need a crypto wallet? =

Yes, you need a USDC wallet on the Base network. Base is an Ethereum L2 with sub-cent fees. Use Coinbase Wallet, MetaMask, or any EVM wallet. Payments arrive as USDC — a stablecoin pegged 1:1 to USD. The plugin can also create a wallet for you with Google or Apple sign-in — no crypto experience required.

= Can I cash out to my bank account? =

Yes. If you created a wallet through the plugin, go to the Earnings tab and click Cash out. Xenarch uses Coinbase Offramp to send USDC directly to your bank account. You see fees and the exact amount you'll receive before confirming. Available in supported countries.

= Will this affect my human visitors? =

No. Only AI bots and automated agents see the payment gate. Human visitors, search engine crawlers (Googlebot, Bingbot), and social preview bots (Twitter, LinkedIn) are never affected.

= Which AI bots does Xenarch detect? =

135+ signatures across 6 categories: AI Search (OAI-SearchBot, PerplexityBot), AI Assistants (ChatGPT-User, Claude-User), AI Agents (ChatGPT Agent, Devin), AI Training (Google-Extended, CCBot), Scrapers (Puppeteer, Ahrefs), and General AI (GPTBot, ClaudeBot). New AI crawlers are auto-detected and categorized.

= Can I choose which bots to charge? =

Yes. Each of the 6 categories has its own toggle. AI Search bots default to free (they bring you referral traffic). Training crawlers default to charged. You can also override individual bots — for example, charge PerplexityBot while allowing the rest of AI Search free.

= What if an AI bot doesn't support x402 payments? =

It receives the 402 Payment Required response and cannot access your content — effectively blocked. Xenarch works as both a payment gate (for x402-capable AI agents) and a blocker (for everything else).

= How much should I charge? =

Default is $0.003/page. Common pricing: $0.001-$0.01 for blog posts, $0.01-$0.10 for premium content, $0.10-$1.00 for API responses. Set per-path pricing rules for different sections of your site.

= Does Xenarch hold my money? =

No. Fully non-custodial. Payments settle as a direct USDC transfer on Base, routed by a third-party x402 facilitator straight from the agent wallet to your wallet. Xenarch is never in the money flow and never has access to your funds.

= Is there a platform fee? =

Zero. 0%, structurally. Xenarch is not in the payment flow — 100% of every payment lands in your wallet.

= Does this work with robots.txt? =

Yes, and it's better. robots.txt asks bots to voluntarily stay away (many ignore it). Xenarch enforces access control at HTTP level. AI crawlers that ignore robots.txt still hit the 402 gate.

= How does this help my SEO? =

Search engine crawlers are never gated. AI Search bots (that help recommend your site) default to allowed. Only training/scraping bots are charged. Your rankings are unaffected.

= What about Cloudflare's pay-per-crawl? =

Xenarch complements Cloudflare's x402 pay-per-crawl tooling. Both use the same x402 protocol. Xenarch adds WordPress-native integration with a full admin dashboard, 6 bot categories, per-bot control, and one-click wallet setup.

== Screenshots ==

1. Settings — Gate section with master toggle and 6 bot category rows (dark and light theme).
2. Manage bots modal with category filter pills and per-bot allow/charge controls.
3. Pricing section with default price and URL-based pricing rules (dark and light theme).
4. Onboarding — wallet setup with Connect, Create (Cash out to your bank), and Enter manually options.
5. Earnings dashboard with stats cards, category breakdown, and transaction history.
6. Status tab with connection diagnostics, bot signature count, and discovery URLs.
7. Terminal showing HTTP 402 response with x-payment headers and JSON gate payload.

== Changelog ==

= 1.7.3 =
* Fix: page-cache plugins (W3 Total Cache, WP Super Cache, LiteSpeed, Comet, etc.) were serving cached HTML responses on x402 payment-proof replays, so the Xenarch middleware never ran → publisher's earnings feed missed payments that the agent's CLI/SDK had already settled on-chain. Plugin now sets `DONOTCACHEPAGE` and sends `Cache-Control: no-store` + `Vary: X-Xenarch-Tx-Hash` headers whenever a request carries the canonical Xenarch replay headers, forcing the cache layer to pass the request through to our middleware. (XEN-384)

= 1.7.2 =
* Fix: Earnings tab now matches the dashboard's `/sites/[id]/activity` defaults — Earned filter on a 30d period (XEN-366), so the merchant doesn't open to a screen full of unpaid-bot noise. Added the two filter-pill rows (Earned/Gated/All + 24h/7d/30d/All) the dashboard ships.
* Fix: replaced the HTML `<table>` activity feed with the dashboard's grid-based `.tx-table > .tx-row` layout. Type pills now use `.tx-type.pay / .gate / .cash / .free` colors (success / warning / error / muted) — identical to dashboard.
* Fix: amount formatting now matches dashboard — "+$X.XX" prefix for positive, "—" for zero, four-decimal precision below $0.01.
* Added prev/next pagination matching the dashboard's pattern.

= 1.7.1 =
* Fix: Status tab was rendering with the old `xn-*` class names (data-row, data-key, data-val, status-card) that v1.7.0 had removed from the CSS — text collapsed inline, status pill / disconnect button were unstyled. Tab now uses the dashboard's class system the same way Settings + Earnings already did.

= 1.7.0 =
* Visual catch-up: plugin admin UI is now a near-1:1 port of dash.xenarch.dev. Same tokens, same component classes (.section / .toggle / .cat-toggle / .bot-type / .stat / .fpills / .rule / .action-seg), same fonts (Inter + JetBrains Mono + Space Grotesk), same rhythm. CSS scoped under #xenarch-admin so WordPress admin's own .btn / .row / .section don't collide. The inherit/customize banner now matches the dashboard's gating page exactly (XEN-369 pattern). (XEN-380)

= 1.6.0 =
* Plugin admin is now a real mirror of dash.xenarch.dev: Earnings tab shows live stats (today / month / all-time), category breakdown, and the recent paid-requests table fetched from the platform. Settings tab lets you edit gating toggles + per-path pricing rules; saves write straight back to the platform via site-token-authed endpoints, and the same state shows up on the dashboard immediately. (XEN-380, XEN-383)
* Wallet card is read-only with a "Change in dashboard →" link, because payout changes go through the email-confirm flow that lives on dash.xenarch.dev.
* New platform endpoints (X-Site-Token authed): GET /v1/sites/me, PUT /v1/sites/me/gating, PUT /v1/sites/me/pricing, GET /v1/sites/me/stats, GET /v1/sites/me/transactions, GET /v1/sites/me/category-breakdown.

= 1.5.0 =
* Plugin is now a thin window into the Xenarch platform. Payout wallet, pricing rules, gating, bot category overrides, earnings, transactions — all live on the backend and are managed in dash.xenarch.dev. WordPress MySQL only stores the site token (the credential pointing at the platform) and a per-server bot detection log.
* Onboarding rewritten: click "Connect with Xenarch", confirm the site on dash.xenarch.dev (no token to copy, no wallet question), come back done. (XEN-380)
* Removed all platform-owned `xenarch_*` options from activation (payout wallet, pricing rules, default price, bot categories/overrides, gate toggles, API key, email, wallet type/network).
* Removed dead REST endpoints (/register, /add-site, the legacy pricing/bot proxy surface) and the unused Reown/WalletConnect bundle. Admin bundle ~152 KB vs. ~250 KB pre-rewrite.
* Settings + Earnings tabs deep-link to the dashboard for now; a follow-up wires the dashboard's editing surface into the plugin admin via site-token-authed reads/writes.

= 1.4.0 =
* Dashboard-first onboarding: the plugin's only first-run question is to paste a site token issued by dash.xenarch.dev. Wallet, pricing rules, gating defaults and bot category overrides are managed in the dashboard — the plugin reads them from the platform. (XEN-380)
* Removed dead `/register` + `/add-site` REST endpoints and the unused WP options (`xenarch_api_key`, `xenarch_email`, `xenarch_wallet_type`, `xenarch_wallet_network`).
* Wallet section in Settings is now read-only with a "Change in dashboard →" link. Plugin no longer asks for a wallet during setup.

= 1.2.1 =
* When the merchant picks "Connect wallet" during onboarding, the plugin registers the connected address as an identity auth method on the Xenarch platform. The same wallet signing in on the dashboard (dash.xenarch.dev) then resolves to the same merchant account, so plugin-created sites appear in the dashboard immediately. (XEN-365)

= 1.2.0 =
* Gating config is now sourced from the Xenarch dashboard. Master toggle and per-category on/off live at the platform; the plugin caches the config in a 60-second transient and enforces the publisher's dashboard choices in real time. (XEN-364)
* If the platform is unreachable, the plugin falls back to the legacy `xenarch_gate_enabled` / `xenarch_bot_categories` wp_options so a temporary outage keeps the publisher's last-known intent in force instead of flipping behaviour silently.

= 1.1.1 =
* Adopt SDK-canonical replay headers: `X-Xenarch-Gate-Id` + `X-Xenarch-Tx-Hash` (replaces the interim `X-Payment-Tx` header from 1.1.0). Gate ID now comes straight from the agent header — no server-side re-derivation.
* Fix platform API base URL: `xenarch.dev` → `api.xenarch.dev`.
* Accept the platform's `status:"paid"` verify response (with `verified`/`valid` fallbacks for forward compatibility).

= 1.1.0 =
* Facilitator-agnostic: pay.json now ships a ranked `facilitators` array (pay-json v1.2) so agents can pick from any x402 facilitator (PayAI, xpay, Ultravioleta, etc.)
* Removed the splitter contract from the payment flow — payments now settle as a direct USDC `transferWithAuthorization` from the agent wallet to the publisher wallet
* Replaced JWT access tokens with stateless on-chain re-verification — no session state, no token caching divergence
* Updated `/.well-known/xenarch.md` to describe the x402 standard flow (sign → submit via facilitator → replay with proof)

= 1.0.0 =
* First public release on the WordPress Plugin Directory
* 6-category bot classification: AI Search, Assistants, Agents, Training, Scrapers, General AI
* Per-bot override controls within each category
* Wallet-based onboarding — no email, no password, no API keys
* Wallet creation with Google or Apple sign-in via Coinbase
* Cash out to fiat via Coinbase Offramp — withdraw USDC to your bank account
* Admin UI with Earnings, Settings, and Status tabs
* Earnings dashboard with category breakdown and transaction history
* 135+ bot signatures with auto-detection and categorization of new AI crawlers
* Database-backed bot detection logging with hit counts and timestamps
* Browser proof challenge for suspicious browser-like traffic
* Per-path pricing rules with WordPress page/post autocomplete
* Auto-save on all settings with toggle snap-back on failure
* Master gate toggle acts as kill switch for all bot categories
* Discovery documents served at /.well-known/pay.json and /.well-known/xenarch.md
* REST API gating — bots hitting wp-json endpoints are also gated
* Access token verification for paid gate receipts
* Dark and light theme support in admin UI
* HTTP 402 payment gate with x402 protocol support
* USDC micropayments on Base L2
* Non-custodial — payments route directly to publisher wallet via smart contract
* 0% platform fee

== Upgrade Notice ==

= 1.0.0 =
First public release.

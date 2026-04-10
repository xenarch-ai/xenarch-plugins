# Xenarch — JED Listing Copy

SEO keyword targets (from Google Ads Keyword Planner, April 8 2026):
- Tier 1: `x402` (2,900), `http 402` (880), `ai web scraping` (720), `ai chat detector` (720), `x402 protocol` (590), `ai bot checker` (480), `402 payment required` (480), `micropayments` (390), `gptbot` (390), `ai scraping` (390), `ai crawlers` (390), `402 status code` (390)
- Tier 2: `stablecoin payments` (260), `cloudflare pay per crawl` (260), `ai bot detector` (260), `chatbot detector` (210), `ai data scraping` (170), `monetize website traffic` (170)
- Tier 3: `pay per crawl` (110), `ai agent payments` (70), `content paywall` (40), `block ai bots` (30), `ai bot detection` (20), `ai content monetization` (20), `ai bot blocker` (10)
- Zero-volume trending: `monetize ai traffic`, `charge ai bots`, `bot paywall`, `bot traffic monetization`, `ai scraping protection`
- Joomla-specific (all near zero, but useful for JED internal search): `joomla bot protection`, `joomla security extension`, `joomla paywall`, `joomla bot detection`

Note: Joomla-specific keywords are near-zero volume. The real traffic comes from platform-agnostic terms. The JED listing page itself will rank on Google for these broader keywords thanks to JED's domain authority.

---

## Extension Name

```
Xenarch
```

Matches XML `<name>` tag. Short and clean. All SEO value comes from the description and tags, not the title — JED indexes description text heavily for search.

---

## Short Description (~150 chars)

```
AI bots scrape your Joomla site for free. Xenarch detects 135+ AI crawlers, sorts them into 6 categories, and charges micropayments per page.
```

141 chars.

---

## Full Description (HTML)

```html
<h3>Monetize AI Bot Traffic on Your Joomla Site</h3>

<p>AI web scraping is draining your content. GPTBot, ClaudeBot, Google-Extended, and over 135 other AI crawlers hit your pages every day. They take what you wrote, feed it to their models, and you get nothing back.</p>

<p>Xenarch takes a different approach: instead of blocking AI bots, it charges them. When an AI crawler requests a page, Xenarch returns an HTTP 402 Payment Required response with machine-readable pricing via the x402 protocol. The bot pays a micropayment in USDC stablecoin on Base L2 and gets access. Settlement takes under two seconds. No accounts, no invoices, no subscriptions — just pay-per-crawl.</p>

<p>Human visitors never see any of this. Neither do search engines. This is a payment gate built specifically for AI agents and automated traffic.</p>

<h3>How It Works</h3>

<ol>
  <li><strong>Detection</strong> — Xenarch identifies 135+ AI crawlers at the server level before Joomla even loads your template. No JavaScript needed. Signatures cover OpenAI, Anthropic, Google, Meta, Perplexity, Amazon, ByteDance, and dozens more.</li>
  <li><strong>Classification</strong> — Detected bots are sorted into 6 categories: AI Search, AI Assistants, AI Agents, AI Training, Scrapers, and General AI. Each category has a separate toggle. You decide which ones pay and which pass through free.</li>
  <li><strong>402 Response</strong> — Gated bots receive an HTTP 402 status code with pricing information following the x402 protocol. The response includes a pay.json URL so the bot knows exactly how to pay.</li>
  <li><strong>Payment</strong> — The AI agent sends a USDC micropayment on Base L2, then retries the request with a payment receipt. Xenarch verifies on-chain and grants access. The whole loop takes under two seconds.</li>
  <li><strong>Per-Bot Overrides</strong> — Within any category, you can override individual bots. Want to let PerplexityBot through free while charging the rest of AI Search? One click in the admin panel.</li>
</ol>

<h3>One-Step Setup</h3>

<p>No registration forms. No email. No API keys to copy-paste.</p>

<ol>
  <li>Install the package through the Joomla Extension Manager.</li>
  <li>Open Components &rarr; Xenarch. Connect your wallet, create one with Google or Apple sign-in, or paste your USDC address.</li>
  <li>Done. Default pricing and bot categories are already configured.</li>
</ol>

<p>The whole thing takes under 60 seconds.</p>

<h3>What You Get</h3>

<ul>
  <li><strong>Non-custodial payments</strong> — Every micropayment goes directly to your wallet through a smart contract on Base. Xenarch never holds your funds, not even for a second.</li>
  <li><strong>0% platform fee</strong> — The smart contract is immutable. 100% of what the bot pays goes to you.</li>
  <li><strong>Cash out to your bank</strong> — Created a wallet through the extension? Cash out USDC to your bank account anytime via Coinbase Offramp. You see fees and the exact payout amount before confirming.</li>
  <li><strong>x402 protocol</strong> — Built on the open x402 standard (HTTP 402 + USDC on Base), backed by the x402 Foundation with Coinbase, Cloudflare, Google, and Visa. Compatible with Cloudflare's pay-per-crawl tooling.</li>
  <li><strong>Flexible pricing</strong> — Set a default price per page, then add URL-specific rules. Charge $0.01 for premium articles, $0.003 for blog posts, or make /public/ pages free. Micropayments start at $0.001.</li>
  <li><strong>6 bot categories with smart defaults</strong> — AI Search bots that drive referral traffic to your site? They pass free by default. Training crawlers extracting your content for model training? Charged by default. You control every category independently.</li>
  <li><strong>135+ bot signatures</strong> — Covers all major AI companies: OpenAI (GPTBot, ChatGPT-User, OAI-SearchBot), Anthropic (ClaudeBot, Claude-User), Google (Google-Extended), Meta, Perplexity, Amazon, ByteDance, and many more. Updated regularly.</li>
  <li><strong>Auto-detection</strong> — Unknown AI crawlers that don't match any signature are automatically identified, categorized, and logged. They show up in the dashboard immediately.</li>
  <li><strong>Search engines always pass</strong> — Googlebot, Bingbot, and other search crawlers are whitelisted. Social preview bots (Twitter, LinkedIn, Facebook) also pass free. Your SEO is never affected.</li>
  <li><strong>Works with any template</strong> — The system plugin intercepts requests at the HTTP level, before your template loads. Zero frontend impact, no JavaScript dependency, no layout changes.</li>
</ul>

<h3>What Gets Detected</h3>

<p>Xenarch classifies traffic across 6 categories:</p>

<ul>
  <li><strong>AI Search</strong> — OAI-SearchBot, Claude-SearchBot, PerplexityBot, Bravebot, and 12 others. These index your content so AI search engines can recommend you. Default: allowed free.</li>
  <li><strong>AI Assistants</strong> — ChatGPT-User, Claude-User, Perplexity-User, and 7 others. A real person asked an AI to look something up on your site. Default: charged.</li>
  <li><strong>AI Agents</strong> — ChatGPT Agent, OpenAI-Operator, Devin, and 6 others. Autonomous agents performing tasks. Default: charged.</li>
  <li><strong>AI Training</strong> — Google-Extended, CCBot, Bytespider, and 10 others. Crawlers collecting data for model training. Default: charged.</li>
  <li><strong>Scrapers</strong> — Crawl4AI, Puppeteer, Ahrefs, LangChain, and 40+ others. Automated scraping tools and SEO crawlers. Default: charged.</li>
  <li><strong>General AI</strong> — GPTBot, ClaudeBot, DeepSeekBot, and 29 others. Bots with mixed or unclear intent. Default: charged.</li>
</ul>

<p>HTTP client libraries (python-requests, curl, etc.) are always gated. Search engines and social preview bots always pass free.</p>

<h3>Who This Is For</h3>

<ul>
  <li>Publishers and media sites whose content is being scraped by AI companies for free</li>
  <li>Joomla site owners who want to monetize website traffic from AI crawlers instead of just blocking it</li>
  <li>API providers looking for API monetization without building billing infrastructure</li>
  <li>Anyone tired of playing whack-a-mole with robots.txt while AI bots ignore it anyway</li>
</ul>

<h3>The x402 Standard</h3>

<p>Xenarch implements the x402 protocol — the open standard that turns the HTTP 402 status code into a real payment rail for machines. Backed by the x402 Foundation (Coinbase, Cloudflare, Google, Visa). Already adopted by Stack Overflow, Nous Research, and thousands of developers. Compatible with Cloudflare's pay-per-crawl infrastructure.</p>

<h3>Third-Party Services</h3>

<p>This extension connects to the Xenarch API (<code>api.xenarch.dev</code>) to create and manage gate transactions, validate payment receipts on-chain, and serve pay.json pricing specifications. The extension auto-registers your site when you connect a wallet. No data is sent before wallet setup.</p>

<ul>
  <li><a href="https://xenarch.com/terms">Xenarch Terms of Service</a></li>
  <li><a href="https://xenarch.com/privacy">Xenarch Privacy Policy</a></li>
</ul>

<p>This extension also connects to:</p>

<p><strong>Coinbase</strong> (<code>api.developer.coinbase.com</code>) — Used for wallet creation via Google/Apple sign-in (Coinbase Embedded Wallets) and cash-out to fiat via Coinbase Offramp. Only triggered when you use these features.</p>
<ul>
  <li><a href="https://www.coinbase.com/legal/user-agreement">Coinbase Terms of Service</a></li>
  <li><a href="https://www.coinbase.com/legal/privacy">Coinbase Privacy Policy</a></li>
</ul>

<p><strong>WalletConnect / Reown</strong> (<code>relay.walletconnect.com</code>) — Used for connecting an external crypto wallet (MetaMask, Coinbase Wallet, etc.) via the WalletConnect protocol. Only triggered when you choose "Connect wallet".</p>
<ul>
  <li><a href="https://walletconnect.com/terms">WalletConnect Terms of Service</a></li>
  <li><a href="https://walletconnect.com/privacy">WalletConnect Privacy Policy</a></li>
</ul>
```

---

## Screenshots (up to 8)

Use the same screenshots from `wordpress/.wordpress-org/` — the admin UI is identical (same React app). Copy and rename:

| Source file | JED upload | Caption |
|---|---|---|
| `screenshot-1.png` | Screenshot 1 | Settings — Master gate toggle and 6 bot category rows with per-category counters (dark and light theme). |
| `screenshot-2.png` | Screenshot 2 | Manage bots modal — Category filter pills, per-bot allow/charge controls, search, and bulk actions. |
| `screenshot-3.png` | Screenshot 3 | Pricing — Default price per page and URL-based pricing rules with FREE option (dark and light theme). |
| `screenshot-4.png` | Screenshot 4 | Onboarding — Three wallet options: Connect wallet, Create for me (cash out to your bank), or Enter manually. |
| `screenshot-5.png` | Screenshot 5 | Earnings — Wallet bar with balance, stats cards, category breakdown grid, and transaction table (dark and light theme). |
| `screenshot-6.png` | Screenshot 6 | Status — Connection diagnostics with site token, server-side detection status, and discovery URLs. |
| `screenshot-7.png` | Screenshot 7 | Terminal — HTTP 402 response from a GPTBot request showing x-payment headers and JSON gate payload. |

**Logo/Icon:** Reuse from `wordpress/.wordpress-org/` — `icon.svg`, `icon-256x256.png`, `icon-512x512.png`. The X constellation mark is the brand icon across all platforms.

Note: The only visual difference between WordPress and Joomla is the browser chrome (Joomla admin has its own sidebar/header). The React panel inside is pixel-identical. If JED reviewers are strict about showing the Joomla admin wrapper, retake screenshots from the Joomla test server (joomla-gate.xenarch.dev).

---

## Metadata

| Field | Value | Notes |
|---|---|---|
| **Version** | `1.0.0` | Matches `<version>` in pkg_xenarch.xml |
| **Joomla compatibility** | Joomla 4, 5, 6 | |
| **PHP compatibility** | PHP 8.1+ | Joomla 4+ requires PHP 8.1 minimum |
| **Type** | Free download | |
| **License** | GPL-2.0-or-later | Matches `<license>` in XML |
| **Category** | Access & Security | Best fit — bot access control + payment gating. Competitors here (BadBot Protection, Stop Bad Bots) only block; we monetize. Differentiates us immediately in category browsing. |
| **Languages** | English | |

### Tags

JED tags are free-form (not limited like WP.org's 5-tag cap). Use all relevant terms:

```
ai bot detection, ai scraping, x402, micropayments, pay per crawl, ai crawlers, bot protection, content monetization, USDC payments, payment gate, ai bot blocker, stablecoin, HTTP 402, ai web scraping, gptbot
```

Tag rationale:
- `ai bot detection` (480 vol cluster) — primary function
- `ai scraping` / `ai web scraping` (720 vol) — the problem we solve
- `x402` (2,900 vol) — protocol name, massive growth
- `micropayments` (390 vol) — payment model
- `pay per crawl` (110 vol) — trending category term, Cloudflare association
- `ai crawlers` (390 vol) — what we detect
- `bot protection` — JED category browser term, what Joomla admins search for
- `content monetization` — the value proposition
- `USDC payments` — payment method specificity
- `payment gate` — what we technically are
- `ai bot blocker` (10 vol) — fallback function for non-x402 bots
- `stablecoin` — crypto-adjacent discovery
- `HTTP 402` (880 vol) — protocol-level term
- `gptbot` (390 vol) — most recognized AI bot name

---

## URLs

| Field | URL | Status |
|---|---|---|
| **Website** | https://xenarch.com | Live |
| **Download** | https://github.com/xenarch-ai/xenarch-plugins/releases | Needs first Joomla release cut |
| **Demo** | https://joomla-gate.xenarch.dev | Verify publicly accessible |
| **Support** | https://github.com/xenarch-ai/xenarch-plugins/issues | Live (public repo) |
| **Documentation** | https://xenarch.com/docs | Verify page exists |

---

## Changelog

```
Version 1.0.0 — April 2026

- 6-category bot classification: AI Search, Assistants, Agents, Training, Scrapers, General AI
- 135+ bot signatures with auto-detection and categorization of new AI crawlers
- Per-bot override controls within each category
- Wallet-based onboarding — no email, no password, no API keys
- Wallet creation with Google or Apple sign-in via Coinbase Embedded Wallets
- Cash out to fiat via Coinbase Offramp
- Admin panel with Earnings, Settings, and Status tabs
- Earnings dashboard with category breakdown and transaction history
- Database-backed bot detection logging with hit counts and timestamps
- Per-path pricing rules with Joomla page autocomplete
- Browser proof challenge for suspicious browser-like traffic
- Auto-save settings with toggle snap-back on failure
- Master gate toggle acts as kill switch for all categories
- Discovery documents at /.well-known/pay.json and /.well-known/xenarch.md
- Dark and light theme support in admin UI
- HTTP 402 payment gate with x402 protocol support
- USDC micropayments on Base L2
- Non-custodial — payments route directly to publisher wallet via smart contract
- 0% platform fee
- Joomla package: com_xenarch component + plg_system_xenarch + plg_webservices_xenarch
```

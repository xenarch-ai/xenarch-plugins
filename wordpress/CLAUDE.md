# WordPress Plugin — Dev Reference

Admin UI and behavior spec for the Xenarch WordPress plugin. For bot classification logic and signature lists, see [`Information/design/bot-classification.md`](../../Information/design/bot-classification.md).

Mockups live in `mockups/`:
- **Primary:** `settings.html`, `earnings.html`, `status.html`, `onboarding.html` — source of truth for visual design
- **States:** `states-settings.html`, `states-earnings.html`, `states-status.html`, `states-onboarding.html` — all possible UI states (loading, empty, error, etc.)

## Admin UI Structure

Three tabs: **Earnings** | **Settings** | **Status**.

### Onboarding (no wallet configured)

The wallet IS the registration. No email, no password, no API keys.

First install → open plugin → **only** the wallet question is shown. No tabs, no settings, nothing else.

- Header: `/xenarch v1.0` + theme toggle
- Subtitle
- Centered: "Where do you want to receive payments?" + "This is how you'll get paid when AI bots access your content."
- 3 wallet option cards (same as wallet section in settings)
- Footer: "One step. That's it. Everything else is configured for you."

On wallet selection → plugin auto-registers with the platform (wallet address + domain) → full UI appears (tabs + all sections).

Mockup: `mockups/onboarding.html`

### Wallet Change Constraints

When a user clicks "Change" on a configured wallet:

- **Own wallet / Connected wallet**: goes back to wallet selection immediately. No constraints.
- **Xenarch wallet with balance = 0**: goes back to wallet selection. Old wallet is discarded.
- **Xenarch wallet with balance > 0**: "Change" is disabled. Show tooltip/note: "Cash out your balance before changing wallet." User must go to Earnings → Cash out → then change.

Disconnecting/changing wallet does NOT delete settings (gate toggles, pricing rules, bot overrides). Only the wallet identity changes.

---

### Settings Tab

Three sections, in order:

1. **Gate** — what to gate
2. **Pricing** — how much to charge
3. **Wallet** — where money goes

---

## Section 1: Gate

### Master Toggle

- Label: **Gate AI bots**
- Description: "All non-human traffic must pay unless allowed below"
- Toggle ON (green) = gating active, bots must pay
- Toggle OFF (grey) = gating disabled, all traffic passes free
- Acts as a kill switch — when switched OFF, all category toggles flip OFF and show `{total} allowed` in grey. Category toggles become non-interactive (disabled) while master is OFF.
- When master is switched back ON, category toggles restore their previous states (stored in `xenarch_bot_categories`)

### Bot Type Rows

Six category rows sit directly below the master toggle. No sub-heading — they belong to the gate section visually.

Each row contains:
- **Category toggle** (smaller than master, 36x20px)
  - ON (green) = bots in this category must pay
  - OFF (grey) = bots in this category pass free
- **Category name** (Space Grotesk 13px 500)
- **Description** (Inter 12px, secondary color)
- **Status counter** (JetBrains Mono 11px) — logic below
- **Chevron** `›` — opens the bot detail modal for this category

#### Counter Logic

The counter color always matches the toggle color:

| Toggle state | Overrides? | Counter text | Color |
|---|---|---|---|
| OFF | n/a | `{total} allowed` | `--muted` (grey, matches OFF toggle) |
| ON, all gated | no | `{total} gated` | `--success` (green, matches ON toggle) |
| ON, some overrides to allow | yes | `{total} bots  {gated} gated` | total in `--muted`, gated in `--success` |

Examples:
- AI Search (OFF): `16 allowed` in grey
- AI Assistants (ON, 2 overrides to allow): `10 bots  8 gated` — "10 bots" grey, "8 gated" green
- AI Agents (ON, no overrides): `8 gated` in green

#### Default Category States

| Category | Default | Description |
|---|---|---|
| AI Search | OFF (allow) | Index your site so AI can recommend it |
| AI Assistants | ON (charge) | Users asking AI about your content |
| AI Agents | ON (charge) | Autonomous AI performing tasks on your site |
| AI Training | ON (charge) | Crawlers collecting data for model training |
| Scrapers | ON (charge) | Automated data extraction tools |
| General AI | ON (charge) | Other AI crawlers with mixed or unclear intent |

#### Always-Allow Note

Below the category rows: "Search engines (Google, Bing) and social previews (Twitter, LinkedIn) always pass through free." in `--muted` 11px.

These are not configurable. Search crawlers and social preview bots are always allowed — see `bot-classification.md` for the full lists.

---

## Bot Detail Modal

Opens when clicking any category row chevron. Single modal instance shared across categories.

### Modal Header

- Title: "Manage bots" (Space Grotesk 16px 500)
- Close button: `×` top right

### Category Pills

Horizontal pill bar directly below the title. Same pattern as earnings period pills (JetBrains Mono 11px, surface background, border radius 6px). Active pill has `--border` background.

Pills: `All` | `AI Search` | `Assistants` | `Agents` | `Training` | `Scrapers` | `General`

- `All` shows every bot from every category in one flat list
- Clicking a pill switches the bot list without closing the modal
- The pill matching the category you entered from is active by default

### Search Bar

Text input below pills: "Search bots..." — filters the bot list by name or company.

### Bulk Actions Bar

Row between search and bot list:
- Left: custom checkbox + "Select all" label
- Right: "Allow selected" and "Charge selected" ghost buttons

Selecting individual bot checkboxes + clicking a bulk action applies that action to all selected bots.

### Bot List

Flex rows (`align-items: center`), each containing:
- **Checkbox** — custom styled (16px, `--dim` border, 3px radius, fills `--success` with checkmark when checked)
- **Bot info** (flex: 1)
  - Bot name (JetBrains Mono 12px, `--fg`)
  - Company name (Inter 12px, `--muted`, inline after bot name)
  - "new" badge (JetBrains Mono 10px, `--success` text on `--success-bg`) — shown for bots detected in the last 7 days
- **Last seen** (JetBrains Mono 11px, `--muted`, right-aligned) — relative timestamp ("2m ago", "5h ago", "3d ago"). Bots sorted by last seen, freshest first.
- **Action control** — segmented `[allow | charge]` button pair
  - Two buttons side by side in a bordered container (1px `--border`, radius 4px)
  - Active button: `--border` background, `--fg` text. If active is "allow": text uses `--success` color via `.is-allow` class.
  - Inactive button: no background, `--muted` text
  - Click inactive side to switch. One click, no confirmation.

#### Per-Bot Override Logic

- By default, each bot inherits the category toggle state
- When you click the opposite action, it creates a per-bot override
- Override always wins over category default
- There is no "reset" button — clicking back to the category default removes the override implicitly

---

## Section 2: Pricing

- Title: **Pricing**
- Description: "How much bots pay per page. Add rules for specific paths."

### Default Price

Row: `Default price  $  [0.003]  per page`

No hint — the section description covers it.

This price applies to ALL gated bot categories. No per-category pricing.

### URL Rules

Draggable rule rows (grip handle `⁞`), each containing:
- `URL contains` label (JetBrains Mono 11px)
- Path input (mono, flex: 1)
- Arrow `→`
- `$` symbol + price input (80px, right-aligned)
- Remove button `×`

Special case: when user enters `0` or `0.00` as price, automatically convert display to `FREE` in `--success` color, uppercase.

"+ add rule" ghost button below the rules. Clicking it appends a new empty rule row in editing state.

### Path Autocomplete

The path input queries WordPress pages, posts, and custom post types as the user types. A dropdown appears below the rule row showing matching paths with their page titles:

```
/blog/                    Blog Archive
/blog/ai-payments-2026    AI Payments in 2026
/blog/micropayments-guide The Micropayments Guide
```

- Path in JetBrains Mono 12px `--fg`, title in Inter 12px `--muted`
- Dropdown: `--bg` background, 1px `--border`, 8px radius
- Clicking a suggestion fills the path input and closes the dropdown
- User can also type a custom path without selecting a suggestion

First match wins. Unmatched paths use the default price.

---

## Section 3: Wallet

Two-phase UI: setup and configured.

### Phase 1: Setup (no wallet configured)

- Title: **Wallet**
- Description: "Where do you want to receive payments?"

Three option cards in a horizontal row, each with title + subtitle:

| Option | Title | Subtitle | On click |
|---|---|---|---|
| Connect wallet | Connect wallet | MetaMask, Coinbase, etc. | Opens WalletConnect modal |
| Create for me | Create for me | Cash out to your bank | Shows confirmation + creates Xenarch-managed wallet |
| Enter manually | Enter manually | Base or Solana address | Shows address input + network dropdown |

Card click behavior — no intermediate steps:

- **Connect wallet** → click fires WalletConnect SDK immediately → on connect, transitions to Phase 2
- **Create for me** → click creates wallet immediately → transitions to Phase 2
- **Enter manually** → click replaces the 3 cards with a single compact row: `[0x address input] [Network ▾] [✓] [×]` in a surface card matching the height of the option cards. ✓ (confirm) goes green on hover, × (cancel) goes red on hover. Cancel returns to cards. Enter key also submits.

### Phase 2: Configured (wallet set)

Setup phase hides, replaced by a compact card showing:
- Wallet address (JetBrains Mono 14px, truncated: `0x9b2e...f104`)
- Badge indicating type:
  - `xenarch wallet` — green, for Xenarch-generated wallets
  - `your wallet` — blue, for manually entered addresses
  - `connected via MetaMask` — blue, for WalletConnect (same blue as "own wallet")
- Note below address:
  - Xenarch wallet: "Your wallet. Cash out to fiat anytime from the Earnings tab."
  - Own/connected: "Settled on-chain directly to your wallet. No funds held by Xenarch."
- "Change" link (right side) — reopens Phase 1

---

## Terminology

- **"gated"** — used for status labels (category counters: "8 gated", transaction type pill: "earn")
- **"charge" / "allow"** — used for action controls (per-bot segmented button, bulk action buttons)
- Both mean the same thing: gated = charge applied. This is intentional — status vs action context.

---

## Interaction Conventions

- **All `×` buttons** turn `--error` (red) on hover — pricing rule remove, modal close, wallet cancel
- **Confirm icon buttons** (✓) turn `--success` (green) on hover
- **Toggles** use `--success` (green) when ON, `--dim` (grey) when OFF
- **Toggle feedback**: no toast or status text. Toggle is self-explanatory. On save failure, toggle snaps back to previous state.

---

## Page Header (PHP-rendered)

The header sits above the React card, rendered by `class-xenarch-admin.php`. Same on all pages (onboarding and main).

```
/xenarch  v1.2.0                          [theme toggle]
Charge the bots that take. Let through the ones that help.
```

- `/xenarch` — Space Grotesk 20px 300
- Version badge — JetBrains Mono 11px, surface bg, surface-border
- Subtitle — Inter 13px, secondary color
- Theme toggle — bare icon, pushed right with `margin-left: auto`
- WP page title (`/xenarch dashboard`) rendered by `add_options_page()`, styled via `.xenarch-page .wrap > h1`
- WP background overridden to `--bg` via `.xenarch-page` body class (added by `admin_body_class` filter)

---

## Auto-Save Behavior

No global save button. All changes save immediately:

- **Toggles** (master + category): save on toggle, snap back on failure
- **Per-bot overrides**: save on click in modal
- **Default price**: save on blur or Enter
- **URL rules**: save on blur, add, remove, or reorder
- **Wallet**: each flow has its own explicit action (connect/create/save)

---

## Earnings Tab

Layout order top to bottom:

### Wallet Row

Compact single-line bar at the top — the first thing the publisher sees. Displays based on wallet type:

- **Xenarch wallet**: `● 0x7a3f...c291  [xenarch wallet]  $891.05 USDC  [Cash out]`
  - Green dot, address (mono 13px), badge (green), balance (mono 13px, `--success`, pushed right via `margin-left: auto`), Cash out button
- **Own wallet / WalletConnect**: `● 0x9b2e...f104  [your wallet]`
  - Green dot, address, badge (blue). No balance, no cash out.

### Stats Cards

Three cards in a row: **Today**, **This month**, **All time**. Each shows dollar amount (mono 24px) and "N paid requests" (11px `--muted`).

### Cash Out Panel

Expandable panel below stats (hidden by default, opens on Cash out click). Contains:
- To address input, Network select, Amount input + MAX button
- Network fee hint
- Send + Cancel buttons

### Category Breakdown

3-column grid showing earnings per bot category, sorted by amount descending. Each cell: category name (Inter 12px `--secondary`, left) + dollar amount (mono 12px `--fg`, right). Surface card, 8px radius.

### Transactions Table

Two sets of filter pills side by side:

| Filter group | Pills | Visibility |
|---|---|---|
| Type filter | `All` · `Earned` · `Cashed` | **Only shown for Xenarch wallets with at least one cash out in the selected period** (own/connected wallets have no withdrawals to filter) |
| Period filter | `24h` · `7d` · `30d` · `All` | Always shown |

Table columns: Type (earn/cash pill), Page (mono 12px), Agent (mono 12px), Amount (mono, right-aligned, `--success` for earn, `--error` for cash), Time (12px `--muted`, right-aligned).

"Load more" ghost button at the bottom.

---

## Status Tab

Single surface card (32px padding, 10px radius) with all diagnostic rows. Read-only, no editable fields.

Data rows use the DS key-value pattern: key (mono 12px `--muted`, 140px fixed) | value (mono 12px `--fg`).

| Key | Value |
|---|---|
| status | Status pill: `connected` (green) or `disconnected` (red) |
| site token | Truncated token (`st_ME9Jb...F_7o`) |
| server-side | `● Active — N bot signatures` or `● Inactive` (green/red dot) |
| l.js | `● Loading on frontend` or `● Not active` (green/red dot) |
| pay.json | Clickable URL to `/.well-known/pay.json` |
| xenarch.md | Clickable URL to `/.well-known/xenarch.md` |

---

## Data Model

### Database Table: `wp_xenarch_bot_log`

Created on plugin activation via `dbDelta()`. Dropped on uninstall.

| Column | Type | Description |
|---|---|---|
| `id` | bigint unsigned PK | Auto-increment |
| `signature` | varchar(255) UNIQUE | Bot UA signature (e.g. "GPTBot", "python-requests") |
| `category` | varchar(50) | Bot category key (ai_search, scrapers, etc.) |
| `company` | varchar(100) | Company name |
| `first_seen` | datetime | When this bot was first detected |
| `last_seen` | datetime | Most recent detection |
| `hit_count` | bigint unsigned | Total requests from this signature |

Indexed on: `signature` (unique), `category`, `last_seen`.

**Write path:** `class-xenarch-gate.php` → `log_bot_detection()` — called after every detection event. Uses UPDATE for known signatures (increments hit_count, updates last_seen), INSERT for new signatures (auto-categorized via `auto_categorize()`).

**Read path:** `class-xenarch-rest.php` → `GET /bot-signatures` — merges static signature metadata with live detection data from this table. Returns `last_seen`, `first_seen`, `hit_count` per bot.

### WordPress Options

| Option key | Type | Description |
|---|---|---|
| `xenarch_site_token` | string | API site token |
| `xenarch_gate_enabled` | bool | Master gate toggle |
| `xenarch_bot_categories` | JSON | Category toggle states: `{"ai_search": "allow", "ai_assistants": "charge", ...}` |
| `xenarch_bot_overrides` | JSON | Per-bot overrides: `{"PerplexityBot": "charge", ...}` |
| `xenarch_default_price` | string | Default price USD (e.g. "0.003") |
| `xenarch_pricing_rules` | JSON | URL rules: `[{"path_contains": "/docs/", "price_usd": "0.01"}, ...]` |
| `xenarch_payout_wallet` | string | USDC payout address |
| `xenarch_wallet_network` | string | Network (base, solana) |
| `xenarch_wallet_type` | string | How wallet was set up: `xenarch`, `connected`, `manual` |
| `xenarch_gate_unknown_traffic` | bool | Gate unknown non-browser traffic |

---

## Detection Flow (Server-Side)

See `bot-classification.md` for full detection priority. Summary:

1. Skip checks (admin, logged-in, `.well-known/`, verified `X-Payment-Tx` header, free pricing rule)
2. Search crawler allowlist → always allow
3. Social preview match → always allow
4. AI signature match → look up category → apply category toggle or per-bot override
5. HTTP client library match → always charge
6. Empty UA → always charge
7. Browser header scoring → challenge or charge
8. Unknown non-browser → configurable

The existing `class-xenarch-bot-detect.php` handles detection. The category/override logic will be added to `class-xenarch-gate.php` to look up `xenarch_bot_categories` and `xenarch_bot_overrides` before deciding to gate.

---

## File Map

| File | Purpose |
|---|---|
| `xenarch.php` | Plugin entry point, hooks |
| `includes/class-xenarch-gate.php` | Server-side gating logic |
| `includes/class-xenarch-bot-detect.php` | UA signature matching, header scoring |
| `includes/class-xenarch-browser-proof.php` | Browser challenge cookie |
| `includes/class-xenarch-admin.php` | Admin page registration |
| `includes/class-xenarch-rest.php` | REST API endpoints |
| `includes/class-xenarch-api.php` | Xenarch platform API client |
| `includes/class-xenarch-discovery.php` | pay.json and .well-known serving |
| `includes/class-xenarch-frontend.php` | Frontend snippet loading |
| `includes/class-xenarch-gate-response.php` | 402/403 response rendering |
| `includes/class-xenarch-payment-proof.php` | On-chain `X-Payment-Tx` verification (replaces JWT access tokens post-XEN-179) |
| `admin-ui/` | React admin dashboard (Vite, TypeScript) |
| `mockups/` | HTML mockups (design source of truth) |

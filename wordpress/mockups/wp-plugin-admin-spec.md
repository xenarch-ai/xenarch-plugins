# WordPress Plugin Admin Panel — Development Spec

## Overview

React app embedded in WP admin. Same approach as WooCommerce — WordPress registers a menu page, gives you a `<div id="xenarch-admin">`, and we mount a React app into it. Full control over the UI inside the WP admin shell.

The dark Protocol Dark theme works inside WP admin. The WP sidebar and top bar are white — our plugin content area is ours. We ignore the WP chrome and let it be.

**Mockups:** `xenarch-plugins/wordpress/mockups/`
- `settings.html` — Settings tab
- `earnings.html` — Earnings tab (all 3 wallet states + withdraw panel)

---

## Screens

### 1. Settings Tab

| Section | Elements |
|---------|----------|
| Gate toggle | On/off toggle. When on, AI crawlers must pay. |
| Default price | `$ [input] per page` — applies to all pages unless overridden. |
| Pricing rules | Ordered list. Each rule: `URL contains [path] → $ [price]` or `→ FREE`. Drag to reorder. First match wins. Add/remove buttons. |
| Wallet | USDC address input + network selector (Base, Solana). Three wallet options below: |

**Wallet options:**
1. **Paste address** — user types/pastes their own `0x...` address
2. **Connect wallet** (primary CTA) — opens WalletConnect/Reown AppKit modal. Supports MetaMask, Coinbase Wallet, Rainbow, 600+ wallets. Address auto-fills from connected wallet.
3. **Generate one for me** — Xenarch generates a keypair server-side. Private key encrypted and stored. User sees the address. Withdraw functionality available in Earnings tab.

### 2. Earnings Tab

| Section | Elements |
|---------|----------|
| Stats cards | 3 cards: Today, This Month, All Time. Each shows $ earned + request count. |
| Wallet bar | Changes based on wallet type (see states below). |
| Transactions table | Type (earn/withdraw), Page path, Agent name, Amount, Time. Period filter pills (24h, 7d, 30d, All). Paginated with "Load more". |

**Wallet bar states:**

| Wallet type | What shows | Withdraw? |
|-------------|-----------|-----------|
| Xenarch-generated | Balance in green, address, "xenarch wallet" badge, Withdraw button | Yes — opens withdraw panel |
| Own (pasted) | "Payments go directly to" + address, "your wallet" badge | No — funds are already in their wallet |
| Connected (WalletConnect) | "Payments go directly to" + address, "connected via MetaMask" badge | No — funds are already in their wallet |

### 3. Withdraw Panel (xenarch wallet only)

Only shown when the user generated a wallet via Xenarch. Opens inline below the balance bar.

| Field | Details |
|-------|---------|
| To address | Any `0x...` or exchange deposit address |
| Network | Base (default), Ethereum, Solana, TRON |
| Amount | USDC input with MAX button |
| Fee note | "Network fee: ~$0.001 (Base). Arrives in ~2 seconds." |
| Actions | Send (green), Cancel |

---

## Tech Stack

| Layer | Choice | Why |
|-------|--------|-----|
| Frontend | React 18 + TypeScript | Same as WooCommerce admin. Battle-tested in WP. |
| Build | Vite | Fast, small output, good for embedding |
| State | React useState/useReducer | Simple enough — no Redux needed |
| Wallet connect | Reown AppKit (`@reown/appkit-react`) | Free, open source, 600+ wallets, Base support |
| Styling | CSS modules or Tailwind (scoped) | Must not leak styles into WP admin |
| API client | fetch | Calls xenarch platform API (api.xenarch.com) |
| WP integration | `wp_enqueue_script` + `wp_localize_script` | Standard WP pattern for React apps |

---

## WalletConnect / Reown AppKit Integration

- **Cost:** Free. No paid tiers.
- **License:** Open source (Reown Community License)
- **Setup:** Register at [reown.com](https://reown.com), get a Project ID (free), add WP domain to allowed origins.
- **Package:** `@reown/appkit-react` + `@reown/appkit-adapter-wagmi`
- **Supported wallets:** MetaMask, Coinbase Wallet, Rainbow, Trust, WalletConnect QR, 600+ more
- **Base L2:** Supported out of the box via chain config
- **UX:** User clicks "Connect wallet" → AppKit modal opens → user picks wallet → address auto-fills

```tsx
import { createAppKit } from '@reown/appkit-react'
import { WagmiAdapter } from '@reown/appkit-adapter-wagmi'
import { base } from 'viem/chains'

const adapter = new WagmiAdapter({
  projectId: 'YOUR_PROJECT_ID',
  chains: [base],
})

createAppKit({
  adapters: [adapter],
  projectId: 'YOUR_PROJECT_ID',
  chains: [base],
  metadata: {
    name: 'Xenarch',
    description: 'AI bot payment gate',
    url: 'https://xenarch.com',
  },
})
```

---

## WordPress Registration

```php
// xenarch.php (main plugin file)
add_action('admin_menu', function () {
    add_menu_page(
        'Xenarch',
        'Xenarch',
        'manage_options',
        'xenarch',
        function () {
            echo '<div id="xenarch-admin"></div>';
        },
        'data:image/svg+xml;base64,...', // X constellation icon
        80
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_xenarch') return;

    $asset = include plugin_dir_path(__FILE__) . 'build/index.asset.php';

    wp_enqueue_script(
        'xenarch-admin',
        plugin_dir_url(__FILE__) . 'build/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_enqueue_style(
        'xenarch-admin',
        plugin_dir_url(__FILE__) . 'build/index.css',
        [],
        $asset['version']
    );

    wp_localize_script('xenarch-admin', 'xenarchConfig', [
        'apiBase' => 'https://api.xenarch.com',
        'siteToken' => get_option('xenarch_site_token'),
        'nonce' => wp_create_nonce('xenarch_admin'),
    ]);
});
```

---

## API Endpoints Needed

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `GET /v1/sites/{id}/settings` | GET | Load current settings (gate on/off, price, rules, wallet) |
| `PUT /v1/sites/{id}/settings` | PUT | Save settings |
| `PUT /v1/publishers/me/payout` | PUT | Set/update payout wallet |
| `POST /v1/publishers/me/wallet/generate` | POST | Generate xenarch-managed wallet |
| `GET /v1/sites/{id}/stats` | GET | Earnings stats (today, month, all time) |
| `GET /v1/sites/{id}/transactions` | GET | Transaction list (paginated, filterable by period) |
| `POST /v1/publishers/me/wallet/withdraw` | POST | Withdraw from xenarch-managed wallet |

---

## File Structure

```
xenarch-plugins/wordpress/
├── xenarch.php                    # Main plugin file (PHP)
├── includes/
│   ├── admin-page.php             # WP admin menu registration
│   ├── api-proxy.php              # Optional: proxy API calls via WP REST
│   └── settings.php               # WP options storage
├── admin/                         # React app source
│   ├── package.json
│   ├── vite.config.ts
│   ├── tsconfig.json
│   ├── src/
│   │   ├── main.tsx               # React mount point
│   │   ├── App.tsx                # Router: Settings / Earnings tabs
│   │   ├── components/
│   │   │   ├── Settings.tsx       # Gate toggle, pricing, wallet
│   │   │   ├── PricingRules.tsx   # Draggable rule list
│   │   │   ├── WalletSection.tsx  # Paste/connect/generate wallet
│   │   │   ├── Earnings.tsx       # Stats + wallet bar + transactions
│   │   │   ├── WithdrawPanel.tsx  # Withdraw form
│   │   │   └── TransactionTable.tsx
│   │   ├── hooks/
│   │   │   ├── useSettings.ts     # Fetch/save settings
│   │   │   ├── useEarnings.ts     # Fetch stats + transactions
│   │   │   └── useWallet.ts       # WalletConnect integration
│   │   ├── lib/
│   │   │   └── api.ts             # API client
│   │   └── styles/
│   │       └── admin.css          # Scoped styles (Protocol Dark)
│   └── build/                     # Vite output (committed for WP)
│       ├── index.js
│       ├── index.css
│       └── index.asset.php
├── .wordpress-org/                # WP marketplace assets
└── mockups/                       # Design mockups
```

---

## Design System Compliance

All UI follows `Information/design/brand/design-system.md`:

| Element | Token |
|---------|-------|
| Background | `#0a0a0a` |
| Primary text | `#f0f0f0` |
| Secondary text | `#888` |
| Surface/cards | `#111` |
| Borders | `#1a1a1a` |
| Headings | Space Grotesk 500 |
| Body text | Inter 400 |
| Data/addresses | JetBrains Mono 400 |
| Success/earn | `#4ade80` |
| Error/withdraw | `#f87171` |
| Primary button | `bg: #f0f0f0, color: #0a0a0a` |
| Secondary button | `border: #1a1a1a, color: #888` |
| Connect wallet CTA | `border: #4ade80, color: #4ade80` |

---

## Implementation Order

1. **PHP scaffold** — plugin file, admin page registration, `<div id="xenarch-admin">`
2. **React scaffold** — Vite + React + TypeScript, mount into div, tabs routing
3. **Settings UI** — gate toggle, default price, save button (API integration)
4. **Pricing rules** — add/remove/reorder rules, persist via API
5. **Wallet paste** — address input, network selector, save
6. **WalletConnect** — Reown AppKit integration, auto-fill address on connect
7. **Generate wallet** — API call, display address, store server-side
8. **Earnings UI** — stats cards, wallet bar (3 states), transaction table
9. **Withdraw panel** — form, send API call, confirmation
10. **Polish** — error handling, loading states, responsive

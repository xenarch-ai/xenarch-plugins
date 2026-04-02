# Coinbase Embedded Wallet + Offramp — WordPress Plugin

## Problem

The "Create for me" button in the WP plugin wallet section throws "not available yet." Publishers who don't have a crypto wallet can't onboard. They need a one-click wallet creation flow and a way to cash out USDC to their bank account.

## Decision

Use **Coinbase CDP Embedded Wallets** for wallet creation and **Coinbase Offramp** for cash-out. This keeps Xenarch permanently non-custodial — Coinbase holds the keys in their secure enclave, the publisher authenticates directly with Coinbase, and Xenarch never has signing authority.

### Why Embedded Wallets (not Server Wallets)

- **Server Wallets**: Xenarch's API key controls all publisher wallets = custodial = regulation (FinCEN MSB)
- **Embedded Wallets**: Publisher authenticates with Coinbase directly = user-custodied = non-custodial for us

### Key Properties

- No KYC for wallet creation (email/Google login only)
- KYC only when cashing out to bank (Coinbase's requirement, not ours)
- Available in 100+ countries
- Wallet created in <500ms
- Publisher can export keys anytime
- Free tier: 5,000 operations/month, then $0.005/operation

## Architecture

```
"Create for me" click
  → Coinbase Embedded Wallet SDK auth modal (email/Google)
  → Wallet created (client-side, no platform API call)
  → Address saved to WP settings (wallet_type: 'coinbase')
  → Publisher starts receiving payments

"Withdraw" click (Earnings tab)
  → Option A: "Cash out to bank" → Coinbase Offramp widget (KYC required)
  → Option B: "Send USDC" → Coinbase SDK signs tx client-side (no KYC)
```

Xenarch is never in the money flow. Wallet creation, signing, and offramp all happen between the publisher's browser and Coinbase's infrastructure.

## Implementation

### Phase 1: Dependencies & SDK Setup

| File | Change |
|------|--------|
| `package.json` | Add `@coinbase/onchainkit` (React SDK with embedded wallets + offramp) |
| `wallet/coinbase.ts` | **New file.** Coinbase SDK singleton init (same pattern as `wallet/config.ts` for AppKit) |
| `types.ts` | Add `cdpProjectId: string` to `XenarchAdmin` interface |
| `App.tsx` | Init Coinbase SDK in useEffect (same pattern as AppKit init) |
| `class-xenarch-admin.php` | Pass `cdpProjectId` via `window.xenarchAdmin` (from platform config API, cached 24h) |
| `admin.css` | Theming overrides for Coinbase modals to match Xenarch design system |

### Phase 2: "Create for me" → Wallet Creation

| File | Change |
|------|--------|
| `wallet/coinbase.ts` | Expose `createCoinbaseWallet()` — opens auth, returns wallet address or null |
| `WalletSection.tsx` | Replace `handleCreateWallet()` throw with `createCoinbaseWallet()` → `saveWallet(address, 'coinbase', 'base')` |
| `Onboarding.tsx` | Same replacement in its `handleCreateWallet()` |

**UX flow:**
1. Publisher clicks "Create for me"
2. Coinbase auth modal (email OTP or Google login)
3. Publisher authenticates → wallet created in <500ms
4. Modal closes → address saved → configured state shown

**Badge:** `wallet_type === 'coinbase'` → green "coinbase wallet" badge (or "xenarch wallet" — TBD based on branding preference).

### Phase 3: Balance Display

| File | Change |
|------|--------|
| `wallet/coinbase.ts` | Add `getUSDCBalance(address)` — reads USDC contract on Base via `viem` (already in deps) |
| `EarningsTab.tsx` | For coinbase wallets, query on-chain balance instead of platform API |

Query USDC balance on-chain using `viem` + Base public RPC. No platform endpoint needed.

### Phase 4: Withdraw

| File | Change |
|------|--------|
| `EarningsTab.tsx` | Modify `renderWithdrawPanel()` — for coinbase wallets, show two options |
| `wallet/coinbase.ts` | Add `sendUSDC(toAddress, amount)` for direct transfers |
| `wallet/coinbase.ts` | Add `openOfframp(amount)` for Coinbase cash-out |
| `admin.css` | Styles for two-option withdraw panel |

**Withdraw panel for Coinbase wallets:**

```
┌─────────────────────────────────────────────┐
│  Withdraw USDC                              │
│                                             │
│  [Cash out to bank]    [Send USDC]          │
│   Via Coinbase          To any address      │
│   KYC required          No KYC              │
└─────────────────────────────────────────────┘
```

- **Cash out to bank**: Opens Coinbase Offramp widget pre-filled with wallet + amount. Coinbase handles KYC, conversion, bank transfer.
- **Send USDC**: Current form (address + network + amount). Transaction signed client-side via Coinbase SDK. Publisher authenticates with Coinbase to approve.

### Phase 5: Platform Config

| File | Change |
|------|--------|
| `xenarch-platform: app/routes/system.py` | Add `cdp_project_id` to `GET /v1/config` response |
| `class-xenarch-admin.php` | Add `get_cdp_project_id()` (same pattern as `get_wc_project_id()`) |

## What We Don't Build

- No platform API for wallet creation (client-side via Coinbase SDK)
- No platform API for withdrawals (client-side signing via Coinbase SDK)
- No key storage anywhere in our infrastructure
- No KYC handling (Coinbase's responsibility)
- No custody infrastructure

## Implementation Order

1. **Research** — Read Coinbase OnchainKit docs, confirm exact React components and API
2. **Setup** — Add dependency, create `wallet/coinbase.ts`, wire init in App.tsx
3. **"Create for me"** — Wire button to Coinbase auth, save address on success
4. **Balance** — On-chain USDC balance query via viem
5. **Withdraw** — Offramp widget + direct USDC transfer
6. **Polish** — Theming, error states, loading states, test on gate.xenarch.dev

## Risks

- **Bundle size**: AppKit already adds ~5MB. Coinbase SDK will add more. May need to revisit code splitting.
- **Coinbase SDK stability**: Embedded Wallets recently reached GA — watch for API changes.
- **Country limitations**: Offramp not available everywhere. Need graceful fallback (show "Send USDC" only).
- **Auth popup blockers**: Coinbase auth opens a popup — some browsers may block it. Need to handle.

## Verification

1. Click "Create for me" → Coinbase auth appears → wallet created → address shows in plugin
2. Connect with WalletConnect → still works (no regression)
3. Enter manually → still works (no regression)
4. Earnings tab shows balance for Coinbase wallet
5. "Cash out to bank" opens Coinbase Offramp
6. "Send USDC" signs and sends transaction
7. All three wallet types coexist: `coinbase`, `connected`, `manual`

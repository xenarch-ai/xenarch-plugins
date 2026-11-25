# xenarch-plugins

CMS plugins that integrate Xenarch payment gates into popular platforms. Each plugin is a thin wrapper around the l.js snippet and pay.json.

## Structure

```
wordpress/     — WordPress plugin (PHP, GPL-2.0+)
shopify/       — Shopify app (Liquid/JS)
cloudflare/    — Cloudflare Worker (JS)
gtm/           — Google Tag Manager template
```

## Licensing

- Root: MIT
- `wordpress/`: GPL-2.0+ (required by WordPress plugin directory)

## Design Principle

Plugins are thin enforcement surfaces over the platform gating API. They authenticate with a per-site `X-Site-Token`, enforce payment (HTTP 402 + on-chain verification), and read their gating settings live from the dashboard-managed platform state. Business logic and the canonical bot-signature catalog live in the platform — `cloudflare/` delegates detection to `/v1/gate-decide` rather than bundling a list. (The historical `l.js` client snippet is parked — XEN-147.)

## Workflow

See root `../CLAUDE.md` for branching, PR, and commit conventions.

## First Run Check

On first interaction with a contributor, verify git hooks are set up by checking `git config core.hooksPath`. If it doesn't return `.githooks`, tell the contributor:

> "This project requires git hooks for commit message enforcement. Can I run `git config core.hooksPath .githooks` to enable them?"

Do not proceed with any code changes until hooks are confirmed active.

## Dev workflow & prod deploy baton

Follow the canonical workspace workflow in `../Information/workflow.md` (Retasc issue → claim → worktree → PR → deploy → validate on prod → merge-commit, never squash).

**Parallel sessions:** before any `kamal deploy` of platform or dashboard, claim the per-service deploy baton in Retasc issue **XEN-524** (via its append-only CLAIM/RELEASE comment log) and merge `main` into your branch first — one session validates on a given prod service at a time. See `../Information/workflow.md` → "Parallel sessions — prod deploy baton".

## Parity guard: no hard-coded Base (XEN-716)

Pay links take USDC and USDT on Base, Ethereum, BNB Chain, Monad and Tron. Name a payment's coin, network and explorer from the networks registry (`GET /v1/networks`, XEN-708) or the payment's own `network` / `token` / `explorer_url`, never a hard-coded Base.

- CI (`.github/workflows/parity-guard.yml`) fails a PR that **adds** a `basescan.org` URL, the words "USDC on Base", the Base USDC contract address or a `USDC_ADDRESS`-style constant outside `.github/parity-guard/allowlist.txt`. Existing lines on main are not checked.
- The allowlist holds only the parts that are Base-only by design: x402 agent payments, bot gating, permit subscriptions, signed link params, Coinbase cash-out, XenPay and the chain registry. Add a path there only for that reason.
- One deliberate line elsewhere can opt out with a trailing `parity-guard: allow <reason>` comment.

In this repo the WooCommerce checkout reads a link's networks from the platform (`class-xenarch-woo.php`); keep its explorer map in step with `GET /v1/networks`.

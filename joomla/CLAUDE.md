# Joomla Plugin — Dev Reference

1:1 port of the WordPress Xenarch plugin to Joomla 5.x — same features, same
UI, same behavior. Two independent projects, no shared code. The WordPress
plugin is the **reference**: when in doubt, match `../wordpress`.

Post-XEN-481 this is a **thin window into the platform** (the WP post-XEN-380
architecture). The plugin stores nothing canonical locally — pricing, gating,
per-bot overrides, payout wallet, earnings and transactions all live on the
platform and are read/written through the per-site `X-Site-Token` surface.
Onboarding is a SIWE claim handoff to `dash.xenarch.dev`; there is no wallet
management, registration, or offramp in the plugin.

For bot classification logic and signature lists, see [`Information/design/bot-classification.md`](../../Information/design/bot-classification.md).

## Architecture

Joomla package (`pkg_xenarch`) containing **2** extensions:

1. **`com_xenarch` (Component)** — admin dashboard (React) + the admin-side
   AjaxController that the dashboard talks to.
2. **`plg_system_xenarch` (System Plugin)** — request interception (bot
   detection, 402 gating, discovery docs, x402 settlement).

> There is **no** external webservices API and **no** `api/` component — the
> WordPress plugin has no external REST surface, so neither does this. The
> admin UI calls the component's AjaxController same-origin.

## Local persistence

**No custom Xenarch DB tables.** The only local state is three component
params in `#__extensions` (the analogue of WP's three `wp_options`):

- `site_token` — pairing credential (sent as `X-Site-Token`)
- `site_id` — platform site UUID
- `browser_proof_secret` — HMAC secret for the browser-challenge cookie

Plus a few behaviour fallbacks the gate reads only when the platform is
unreachable (`gate_enabled`, `gate_unknown_traffic`, and the legacy
`bot_categories`/`bot_overrides`/`pricing_rules` which are never written
post-pivot), and `pay_json_last_good` (the Discovery outage snapshot).

Transient-equivalents (gating config, gate payloads, payment-proof results,
the pay.json site snapshot) use Joomla's native cache via
`src/plugins/system/xenarch/src/Cache.php` — not a bespoke table.

## File Map

| Path | Purpose |
|------|---------|
| `src/plugins/system/xenarch/src/Extension/Xenarch.php` | Gate + discovery (onAfterInitialise / onAfterDispatch) |
| `src/plugins/system/xenarch/src/BotDetect.php` | UA signature matching, header scoring (137 sigs, 1:1 with WP) |
| `src/plugins/system/xenarch/src/BrowserProof.php` | Browser challenge cookie (HMAC) |
| `src/plugins/system/xenarch/src/PaymentProof.php` | On-chain proof verify (canonical headers + X-PAYMENT); fail-closed on 4xx |
| `src/plugins/system/xenarch/src/ApiClient.php` | Platform API client — site_token only, `/v1/sites/me/*` |
| `src/plugins/system/xenarch/src/ApiException.php` | Status-aware platform error (fail-closed vs fail-open) |
| `src/plugins/system/xenarch/src/Cache.php` | Native-cache transient wrapper |
| `src/plugins/system/xenarch/src/GateResponse.php` | 402/403 response rendering |
| `src/plugins/system/xenarch/src/Discovery.php` | pay.json / xenarch.md (sourced from platform `getMySite`) |
| `src/administrator/components/com_xenarch/src/Controller/AjaxController.php` | Admin REST surface (analogue of `class-xenarch-rest.php`) |
| `src/administrator/components/com_xenarch/src/Helper/XenarchHelper.php` | Param read/write |
| `src/administrator/components/com_xenarch/tmpl/dashboard/default.php` | React mount point + thin-snapshot bootstrap |
| `src/media/com_xenarch/` | Built JS/CSS bundle + joomla.asset.json |
| `admin-ui/` | React dashboard (Vite, TS) — copied from WP, only `api.ts` + onboarding URL differ |

## WordPress → Joomla mapping

| WordPress | Joomla | Where |
|-----------|--------|-------|
| `template_redirect` priority 0 (discovery) | `onAfterInitialise` | System plugin |
| `template_redirect` priority 1 (gating) | `onAfterDispatch` | System plugin — runs there so a 404 (which throws before the event) is never gated, matching WP's `is_404()` skip |
| `get_option` / `update_option` | `ComponentHelper::getParams()` / `XenarchHelper` | throughout |
| `wp_remote_post/get` | `HttpFactory::getHttp()->post/get` | `ApiClient` |
| `get_transient` / `set_transient` | `Cache` (Joomla native cache) | gate, PaymentProof, Discovery |
| `WP_Error` `status` | `ApiException::$status` | fail-closed-on-4xx |
| `X-WP-Nonce` | Joomla session form token (`&<token>=1`) | admin UI `api.ts` |
| `add_option('xenarch_pay_json_last_good')` | `pay_json_last_good` component param | Discovery outage fallback |

The admin REST endpoints the React app calls (via `task=ajax.<endpoint>`):
`settings`, `claimexchange`, `disconnect`, `site`, `gating`, `pricing`,
`stats`, `transactions`, `categorybreakdown`, `wallets`, `payoutwallet` —
the 1:1 set of WP's `/xenarch/v1/*` routes.

## Admin UI

Shared 1:1 with WordPress (`admin-ui/src` is copied from `../wordpress`).
Only differences:
- `api.ts` — Joomla transport (`task=ajax.*` + form token) instead of WP REST + `X-WP-Nonce`.
- `App.tsx` / `Onboarding.tsx` — admin URL is `?option=com_xenarch`, claim `integration=joomla`.

Build: `cd admin-ui && npm install && npm run build`
Deploy: `build-release.sh` copies `dist/` into `src/media/com_xenarch/{js,css}` and zips the package.

## Build Package

```bash
./scripts/build-release.sh   # → src/packages/pkg_xenarch.zip
```

## Test Server

- Docker: `docker compose up -d` (MySQL 8 + Joomla 5.x)
- Domain: `joomla-gate.xenarch.dev` (same Hetzner VPS as the WP test server; see secrets.md)

## External dependency

Onboarding redirects to `dash.xenarch.dev/sites/claim?...&integration=joomla`.
The dashboard must recognize `integration=joomla` for the claim→site_token
exchange to complete.

## Workflow

See root `../CLAUDE.md` for branching, PR, and commit conventions.

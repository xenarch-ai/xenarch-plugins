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

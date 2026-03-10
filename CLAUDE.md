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

Plugins should be minimal — load `l.js` with the publisher's site ID and optionally manage `pay.json` generation. Business logic lives in the platform and the JS snippet.

## Workflow

See root `../CLAUDE.md` for branching, PR, and commit conventions.

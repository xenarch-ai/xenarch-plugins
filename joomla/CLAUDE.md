# Joomla Plugin — Dev Reference

Complete port of the WordPress Xenarch plugin to Joomla 5.x. Same features, same UI, same behavior. Two independent projects — no shared code.

For bot classification logic and signature lists, see [`Information/design/bot-classification.md`](../../Information/design/bot-classification.md).

## Architecture

Joomla package (`pkg_xenarch`) containing 3 extensions:

1. **`com_xenarch` (Component)** — Admin panel + API controllers + database
2. **`plg_system_xenarch` (System Plugin)** — Request interception (bot detection, 402 gating, discovery docs)
3. **`plg_webservices_xenarch` (Web Services Plugin)** — REST API route registration

## File Map

| Path | Purpose |
|------|---------|
| `src/plugins/system/xenarch/src/Extension/Xenarch.php` | Main system plugin (gate + discovery) |
| `src/plugins/system/xenarch/src/BotDetect.php` | UA signature matching, header scoring |
| `src/plugins/system/xenarch/src/BrowserProof.php` | Browser challenge cookie |
| `src/plugins/system/xenarch/src/AccessToken.php` | Bearer token validation |
| `src/plugins/system/xenarch/src/ApiClient.php` | Xenarch platform API client |
| `src/plugins/system/xenarch/src/GateResponse.php` | 402/403 response rendering |
| `src/plugins/system/xenarch/src/Discovery.php` | pay.json and xenarch.md serving |
| `src/plugins/webservices/xenarch/src/Extension/Xenarch.php` | REST API route registration |
| `src/api/components/com_xenarch/src/Controller/` | 16 REST API controllers |
| `src/administrator/components/com_xenarch/` | Admin component (view, helper, templates) |
| `src/administrator/components/com_xenarch/src/Helper/XenarchHelper.php` | Settings read/write helper |
| `src/administrator/components/com_xenarch/tmpl/dashboard/default.php` | React mount point template |
| `src/media/com_xenarch/` | JS/CSS assets + joomla.asset.json |
| `admin-ui/` | React admin dashboard (Vite, TypeScript) |
| `mockups/` | HTML mockups (design source of truth) |

## WordPress → Joomla Hook Mapping

| WordPress | Joomla | Where |
|-----------|--------|-------|
| `template_redirect` (priority 1) | `onAfterRoute` | System plugin — bot gating |
| `template_redirect` (priority 0) | `onAfterInitialise` | System plugin — /.well-known/ serving |
| `wp_enqueue_scripts` | `onBeforeCompileHead` | System plugin — l.js (disabled) |
| `register_rest_route()` | `onBeforeApiRoute` + Route objects | Web services plugin |
| `get_option()` / `update_option()` | `ComponentHelper::getParams()` / XenarchHelper | Throughout |
| `wp_remote_post/get()` | `HttpFactory::getHttp()->post/get()` | ApiClient |
| `get_transient()` / `set_transient()` | `#__xenarch_cache` table | AccessToken, gate caching |
| `$wpdb` queries | `DatabaseInterface` with query builder | Bot logging, cache |
| `X-WP-Nonce` | `X-CSRF-Token` (Joomla session form token) | Admin UI API calls |

## Settings Storage

Settings are stored in the `params` JSON column of `#__extensions` (component row). Same keys as WordPress `wp_options` but without the `xenarch_` prefix.

## Database Tables

- `#__xenarch_bot_log` — Same schema as WordPress (`wp_xenarch_bot_log`)
- `#__xenarch_cache` — Replaces WordPress transients (`cache_key`, `cache_value`, `expires_at`)

## Admin UI

Identical React app to WordPress. Only differences:
- `api.ts` — Uses configurable CSRF header instead of hardcoded `X-WP-Nonce`
- `types.ts` — Added `csrfHeaderName` to `XenarchAdmin` interface

Build: `cd admin-ui && npm install && npm run build`
Deploy: Copy `admin-ui/dist/` to `src/media/com_xenarch/js/` and `css/`

## Test Server

- Docker: `docker compose up -d` (MySQL 8 + Joomla 5.4)
- Domain: `joomla-gate.xenarch.dev`
- Same Hetzner VPS as WordPress test server (see secrets.md for IP)
- Port: 8889 (nginx reverse proxy)

## Build Package

```bash
./scripts/build-release.sh
```

Produces `src/packages/pkg_xenarch.zip` for installation via Joomla Extension Manager.

## Workflow

See root `../CLAUDE.md` for branching, PR, and commit conventions.

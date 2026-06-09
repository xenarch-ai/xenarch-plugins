# Xenarch Cloudflare Worker

Tier-2 **edge gating** for Xenarch. Gates AI bots in front of static sites
(Hugo, Jekyll, plain HTML) that have **no server to run middleware**. It enforces
payment identically to the other Xenarch surfaces — same canonical proof headers,
same vanilla x402 inbound, same dashboard-driven settings.

## How it works

On every request the Worker, before your origin is hit:

1. **Skips** `/.well-known/*`, `/pay.json`, and static assets.
2. **Canonical proof** — if the request carries `X-Xenarch-Gate-Id` +
   `X-Xenarch-Tx-Hash`, verifies it on-chain (`POST /v1/gates/{id}/verify`) and
   lets it through.
3. **Vanilla x402** — if the request carries an `X-Payment` voucher, mints the
   gate for that path and settles it (`POST /v1/gates/{id}/settle-x402`).
4. **Decision** — asks the platform for this site's *effective* verdict
   (`GET /v1/gate-decide` authenticated with the site token). Detection runs
   **server-side** against the canonical signature catalog, so the Worker bundles
   no list and can never drift. `allow` → origin.
5. **Charge** — returns the platform's `402` x402 envelope (the same body every
   surface emits).

### Settings live in the dashboard

The Worker is **stateless**. Which categories/bots are gated is configured in the
dashboard (`/bots` global, `/sites/[id]/gating` per-site) and read live — a change
takes effect on the next request, **no redeploy**.

### Scope

The Worker gates **self-announcing, signature-matched bots**. It does not run the
header-scoring / browser-challenge path the server-side surfaces use, so ambiguous
browser-like traffic and empty User-Agents pass through (fail-open). Use a
server-side integration if you need to catch spoofed browsers.

## Install

In the publisher's own Cloudflare account:

```sh
npm install

# Site token from the dashboard: Add site → Cloudflare → copy the claim.
npx wrangler secret put XENARCH_SITE_TOKEN

npx wrangler deploy
```

Then bind the Worker to your zone — uncomment the `routes` block in
`wrangler.toml` (or add a route in the Cloudflare dashboard):

```toml
routes = [
  { pattern = "example.com/*", zone_name = "example.com" },
]
```

## Config

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `XENARCH_API_BASE` | var | `https://xenarch.dev` | Platform API base |
| `XENARCH_SITE_TOKEN` | **secret** | — | Authenticates the Worker as a specific site (`X-Site-Token`) |

## Test

```sh
npm test
```

## License

MIT.

# Xenarch Edge sandbox

A live, self-contained x402 gating test site for **edge.xenarch.dev**. It runs the
**production** Worker gating core (`../src/gate.js`) in front of four bundled pages:

| Path | Gated? | Price |
|------|--------|-------|
| `/` (landing) | free | — |
| `/dispatch/crawlers-learned-to-pay/` | gated | 0.003 USDC |
| `/dispatch/field-guide-toll-agents/` | gated | 0.005 USDC |
| `/dispatch/the-toll-economy/` | gated | 0.01 USDC |

Content is fictional. The point is a real edge to test against: browsers read
everything free; a self-identifying agent gets `HTTP 402` on the dispatches.

## How it's wired

- `worker.js` calls the shared `handleGate()` with `passthrough = env.ASSETS.fetch`,
  so an allowed request is served from the bundled `public/` assets — no separate
  origin, no fetch loop.
- `run_worker_first = true` makes the gate run before any asset is served.
- `XENARCH_FREE_PATHS = "/"` keeps the landing free for everyone.
- Same `X-Site-Token` → `/v1/gate-decide` (A2) verdict as every other surface.

## Deploy

```sh
cd ..            # xenarch-plugins/cloudflare
npm install      # wrangler 4.x
cd test-site

# Site token for the edge.xenarch.dev site (dashboard → Add site → Cloudflare):
npx wrangler secret put XENARCH_SITE_TOKEN

npx wrangler deploy   # custom_domain route auto-provisions edge.xenarch.dev DNS + TLS
```

## Test it

```sh
./test.sh                      # runs the full matrix against edge.xenarch.dev
./test.sh https://my.preview   # or any base URL
```

Expected:

| Request | Expect |
|---------|--------|
| browser UA on `/` | 200 |
| browser UA on a dispatch | 200 |
| `GPTBot` on `/` | 200 (free path) |
| `GPTBot` on a dispatch | 402 |
| `python-requests` on a dispatch | 402 |
| `Googlebot` on a dispatch | 200 (search allowed by default) |
| canonical proof / `X-Payment` voucher on a dispatch | 200 after settle |

# xenarch-plugins

CMS plugins for the Xenarch payment network.

| Plugin | Platform | Language |
|--------|----------|----------|
| [WordPress](wordpress/) | WordPress 6.0+ | PHP |
| [Shopify](shopify/) | Shopify | Liquid/JS |
| [Cloudflare](cloudflare/) | Cloudflare Workers | JS |
| [GTM](gtm/) | Google Tag Manager | Template |

## How It Works

Each plugin enforces payment (HTTP 402 + on-chain verification of USDC transfer) against the platform's gating API. The server-side plugins (WordPress, Joomla) run bot detection locally and serve a `pay.json`; the [Cloudflare](cloudflare/) Worker gates at the CDN edge for static sites with no server, delegating detection to the platform (`/v1/gate-decide`) so it never drifts from the canonical signature catalog.

> **Note (2026-04-27):** historical references to a client-side `l.js` snippet are obsolete. The `xenarch-js` repo was parked (XEN-147). Frontend-script hooks remain commented out in the WP and Joomla plugins for future resurrection.

## License

- Most plugins: MIT
- WordPress: GPL-2.0+ (required by WP plugin directory)

# xenarch-plugins

CMS plugins for the Xenarch payment network.

| Plugin | Platform | Language |
|--------|----------|----------|
| [WordPress](wordpress/) | WordPress 6.0+ | PHP |
| [Shopify](shopify/) | Shopify | Liquid/JS |
| [Cloudflare](cloudflare/) | Cloudflare Workers | JS |
| [GTM](gtm/) | Google Tag Manager | Template |

## How It Works

Each plugin enforces payment server-side (HTTP 402 + on-chain verification of USDC transfer) and serves a `pay.json` for AI agent pricing.

> **Note (2026-04-27):** historical references to a client-side `l.js` snippet are obsolete. The `xenarch-js` repo was parked (XEN-147). Frontend-script hooks remain commented out in the WP and Joomla plugins for future resurrection.

## License

- Most plugins: MIT
- WordPress: GPL-2.0+ (required by WP plugin directory)

# xenarch-plugins

CMS plugins for the Xenarch payment network.

| Plugin | Platform | Language |
|--------|----------|----------|
| [WordPress](wordpress/) | WordPress 6.0+ | PHP |
| [Shopify](shopify/) | Shopify | Liquid/JS |
| [Cloudflare](cloudflare/) | Cloudflare Workers | JS |
| [GTM](gtm/) | Google Tag Manager | Template |

## How It Works

Each plugin loads the Xenarch snippet (`l.js`) on your site and optionally generates a `pay.json` file for AI agent pricing.

## License

- Most plugins: MIT
- WordPress: GPL-2.0+ (required by WP plugin directory)

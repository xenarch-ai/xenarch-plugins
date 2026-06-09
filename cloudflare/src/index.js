/**
 * Xenarch Cloudflare Worker — Tier-2 edge gating (XEN-464).
 *
 * Implements Xenarch's canonical gating contract at the Cloudflare edge so
 * static sites (Hugo / Jekyll / plain HTML) with no server-side middleware can
 * still gate AI bots. It gates *identically* to every other enforcement surface
 * (the Joomla / WordPress plugins, the Python SDK) — same canonical proof
 * headers, same vanilla x402 inbound, same dashboard-driven settings.
 *
 * Design (locked, XEN-464):
 *   - Option A: detection is delegated to the platform's /v1/gate-decide.
 *     The Worker bundles NO signature list, so it can never drift from the
 *     canonical catalog (app/data/bot_signatures.py). The SDK copies drifted;
 *     this can't.
 *   - Option A2: gate-decide is called with the site's X-Site-Token, so it
 *     returns the *effective site-level* verdict (per-site overrides honoured,
 *     exactly like WP/Joomla), not just publisher-level defaults.
 *   - Pull, not push: all settings live in the platform DB and are edited from
 *     the dashboard. The Worker is stateless; a dashboard change takes effect on
 *     the next request (modulo the short caches in gate.js). No redeploy.
 *
 * Bilingual 402 (matches every other surface):
 *   - Canonical proof: X-Xenarch-Gate-Id + X-Xenarch-Tx-Hash -> /verify.
 *   - Vanilla x402:    X-Payment voucher                     -> /settle-x402.
 *   - Outbound 402:    the platform's superset envelope from POST /v1/gates.
 *
 * Config (wrangler vars / secrets):
 *   - XENARCH_SITE_TOKEN (secret) — authenticates the Worker as a site.
 *   - XENARCH_BROWSER_PROOF_SECRET (secret) — HMAC key for the browser-proof
 *                                    challenge cookie. Required for the challenge
 *                                    band; absent → that band degrades to allow.
 *   - XENARCH_API_BASE   (var)    — platform base, default https://xenarch.dev.
 *   - XENARCH_FREE_PATHS (var)    — optional comma-separated paths never gated
 *                                    (e.g. "/,/about" to keep a homepage free).
 *
 * Full-ladder parity (XEN-477): the Worker now gates byte-for-byte like the
 * WordPress plugin. It forwards the request's headers + a validated browser-proof
 * flag to /gate-decide, which runs the SAME detect_full ladder WP runs —
 * empty-UA, named-bot signatures, the search-crawler allowlist, the HTTP-client
 * (fetcher) list, browser-proof short-circuit, Mozilla/ header-scoring, and
 * unknown-non-browser. Verdicts are allow | charge (402) | challenge; the
 * challenge band serves the browser-proof JS interstitial here at the edge
 * (mirror of WP render_browser_challenge). No signature list is bundled — only
 * the ladder's *inputs* are gathered at the edge; the catalog + scoring live in
 * the platform (app/services/bot_detect.py), so the Worker can never drift.
 */
import { handleGate } from "./gate.js";

export default {
  async fetch(request, env, ctx) {
    // Production passthrough = the publisher's origin behind this route.
    return handleGate(request, env, { waitUntil: ctx.waitUntil.bind(ctx) });
  },
};

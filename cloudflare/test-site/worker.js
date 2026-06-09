/**
 * Xenarch Edge sandbox Worker (XEN-464 test fixture).
 *
 * Runs the SAME gating core as the production Worker (../src/gate.js). The only
 * difference: an allowed request is served from this Worker's bundled static
 * assets (env.ASSETS) instead of passing through to a separate origin. That
 * makes the sandbox self-contained — one `wrangler deploy` ships the gate AND
 * the four pages, with no second origin and no risk of the Worker fetching its
 * own route in a loop.
 *
 * `run_worker_first = true` (wrangler.toml) ensures this Worker gates EVERY
 * request before any static asset is served, so a bot gets a 402 even on a path
 * that has a matching asset. The free landing page is allowed via
 * XENARCH_FREE_PATHS = "/".
 */
import { handleGate } from "../src/gate.js";

export default {
  async fetch(request, env, ctx) {
    return handleGate(request, env, {
      passthrough: (req) => env.ASSETS.fetch(req),
      waitUntil: ctx.waitUntil.bind(ctx),
    });
  },
};

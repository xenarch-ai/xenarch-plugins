/**
 * Xenarch edge-gating core (XEN-464) — shared by the production Worker
 * (src/index.js) and the test site (test-site/worker.js) so both run the
 * IDENTICAL gating logic. The only thing that varies per deployment is how an
 * allowed request is served:
 *   - production: pass through to the publisher's origin — `fetch(request)`
 *   - test site:  serve bundled static assets — `env.ASSETS.fetch(request)`
 *
 * Reports activity back to the platform exactly like the WordPress/SDK surfaces
 * so the dashboard shows the site as connected + every detected bot:
 *   - `Origin: https://<host>` on POST /v1/gates → platform backfills the site
 *     domain on first contact (it 409s a domain-less site without it) and builds
 *     the correct gate resource URL.
 *   - POST /v1/sites/me/bot-detections (fire-and-forget) for every known-signature
 *     bot → drives the dashboard's Bots panel + per-site request counts.
 */

// Static assets are never paywalled content.
const STATIC_EXT = new Set([
  "ico", "png", "jpg", "jpeg", "gif", "svg", "webp", "avif",
  "css", "js", "mjs", "map", "woff", "woff2", "ttf", "eot",
  "xml", "txt", "pdf", "mp4", "webm", "mp3", "wasm",
]);

const GATE_TTL_MS = 30 * 60 * 1000; // gate cached 30 min per path.
const DECIDE_TTL_MS = 60 * 1000; // gating verdict cached 60 s.
const SITE_TTL_MS = 60 * 1000; // site detail (price rules + wallet) cached 60 s.
const EXISTS_TTL_MS = 60 * 1000; // origin existence check cached 60 s per path.

// Ranked facilitator stack emitted in pay.json — mirror of WP
// class-xenarch-discovery.php DEFAULT_FACILITATORS (pay-json v1.2 shape).
const FACILITATORS = [
  { name: "payai", url: "https://facilitator.payai.network", priority: 1, spec_version: "v1" },
  { name: "xpay", url: "https://facilitator.xpay.dev", priority: 2, spec_version: "v1" },
  { name: "ultravioleta", url: "https://x402.ultravioleta.dev", priority: 3, spec_version: "v2" },
];

// Browser-proof challenge (mirror of WP class-xenarch-browser-proof.php).
const PROOF_COOKIE = "xenarch_browser_proof";
const PROOF_TTL = 900; // seconds — WP DEFAULT_TTL.

// Bound what we forward to /gate-decide (a public endpoint). Real UAs are well
// under 1 KB; truncating keeps a giant-UA/header client from burning platform
// CPU (and matches the platform's own caps, so we don't fail-open on a 422).
const MAX_UA_LEN = 2048;
const MAX_HEADER_VALUE_LEN = 1024;

// Request headers forwarded to /gate-decide for Mozilla/ header-scoring. Exact
// set the WP plugin maps in class-xenarch-gate.php get_request_headers().
const FORWARD_HEADERS = [
  "Accept",
  "Accept-Language",
  "Accept-Encoding",
  "Sec-Fetch-Mode",
  "Sec-Fetch-Dest",
  "Sec-Fetch-Site",
  "Sec-Fetch-User",
  "Sec-Ch-Ua",
  "Upgrade-Insecure-Requests",
];

// Per-isolate TTL cache. Ephemeral and best-effort — a cold isolate just
// re-fetches. Keeps the hot path off the platform without any shared state.
// Cache keys are attacker-controlled (UA, path), so the size is hard-capped:
// a bot rotating UAs/paths must not grow this Map unbounded.
const MAX_MEMO = 5000;
const memo = new Map();
function cacheGet(key) {
  const e = memo.get(key);
  if (!e) return undefined;
  if (Date.now() > e.exp) {
    memo.delete(key);
    return undefined;
  }
  return e.val;
}
function cacheSet(key, val, ttlMs) {
  // Evict oldest entries (Map preserves insertion order) before inserting.
  if (memo.size >= MAX_MEMO) {
    for (const k of memo.keys()) {
      memo.delete(k);
      if (memo.size < MAX_MEMO) break;
    }
  }
  memo.set(key, { val, exp: Date.now() + ttlMs });
}

/** Test-only: clear the per-isolate caches so cases don't leak state. */
export function __resetGateCaches() {
  memo.clear();
  lastGoodSite = null;
}

/**
 * Parse the XENARCH_FREE_PATHS var: a comma-separated list of paths the Worker
 * never gates (always served, even to bots). An entry ending in `/*` matches by
 * prefix; otherwise it's an exact match. Example: "/,/about,/blog/*".
 */
function parseFreePaths(raw) {
  if (!raw) return [];
  return String(raw)
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
}

function isFreePath(path, freePaths) {
  for (const p of freePaths) {
    if (p.endsWith("/*")) {
      const prefix = p.slice(0, -1); // keep trailing slash: "/blog/"
      if (path === prefix.slice(0, -1) || path.startsWith(prefix)) return true;
    } else if (path === p) {
      return true;
    }
  }
  return false;
}

/**
 * The gate. Returns a Response.
 * @param opts.passthrough (request) => Promise<Response> — how to serve an
 *   allowed request. Defaults to `fetch(request)` (origin passthrough).
 * @param opts.freePaths string[] — paths never gated. Defaults to
 *   parsing env.XENARCH_FREE_PATHS.
 * @param opts.waitUntil (promise) => void — schedule fire-and-forget background
 *   work (the bot-detections report) so it completes after the response is sent.
 *   Pass ctx.waitUntil; defaults to a no-op (report still attempted inline).
 */
export async function handleGate(request, env, opts = {}) {
  const passthrough = opts.passthrough || ((req) => fetch(req));
  const waitUntil = opts.waitUntil || (() => {});
  const apiBase = (env.XENARCH_API_BASE || "https://api.xenarch.dev").replace(/\/$/, "");
  const siteToken = env.XENARCH_SITE_TOKEN;

  // Not configured yet — never gate.
  if (!siteToken) return passthrough(request);

  const url = new URL(request.url);
  const path = url.pathname;
  const host = url.host; // the publisher's hostname, e.g. edge.xenarch.dev

  // Publisher-declared free paths (e.g. the landing page) — never gated.
  const freePaths = opts.freePaths || parseFreePaths(env.XENARCH_FREE_PATHS);
  if (isFreePath(path, freePaths)) return passthrough(request);

  // Discovery docs: serve pay.json + xenarch.md GENERATED from the platform
  // (mirror of WP class-xenarch-discovery.php), so a static origin with no
  // discovery files still advertises payment. Other /.well-known/ paths (ACME
  // challenges, etc.) pass through untouched.
  if (path === "/.well-known/pay.json" || path === "/pay.json") {
    return servePayJson(apiBase, siteToken, host, passthrough, request);
  }
  if (path === "/.well-known/xenarch.md") {
    return serveXenarchMd(apiBase, siteToken, host, passthrough, request);
  }
  if (path.startsWith("/.well-known/")) {
    return passthrough(request);
  }
  // Never gate static assets.
  const dot = path.lastIndexOf(".");
  const ext = dot >= 0 ? path.slice(dot + 1).toLowerCase() : "";
  if (ext && STATIC_EXT.has(ext)) return passthrough(request);

  const h = request.headers;

  // 1. Canonical proof present → verify on-chain → let through.
  //    Transport / 5xx errors fail OPEN; a 4xx rejection falls through to
  //    the gate.
  const gateId = h.get("X-Xenarch-Gate-Id");
  const txHash = h.get("X-Xenarch-Tx-Hash");
  if (gateId && txHash) {
    if (await verifyCanonical(apiBase, siteToken, gateId, txHash)) {
      return passthrough(request);
    }
  }

  // 2. Vanilla x402 voucher → mint/lookup the gate for this path → settle.
  //    No fail-open: an unsettled voucher is not a paid request.
  const xPayment = h.get("X-Payment");
  if (xPayment) {
    const gate = await getOrCreateGate(apiBase, siteToken, path, host, "x402_x_payment");
    if (gate && gate.gate_id) {
      if (await settleX402(apiBase, siteToken, gate.gate_id, xPayment)) {
        return passthrough(request);
      }
    }
  }

  // 3. FREE path: a platform price rule (or the site default) of $0 is served
  //    free to everyone — bots included — BEFORE detection, mirror of WP
  //    is_free_path (class-xenarch-gate.php:97). Uses the platform's own
  //    fnmatch/longest-match pricing semantics so the edge never disagrees with
  //    what the dashboard set. The static XENARCH_FREE_PATHS env list (handled
  //    above) is an additional deploy-time convenience on top of this.
  const site = await getSiteDetail(apiBase, siteToken);
  if (site && isFreeByRule(path, site)) {
    return passthrough(request);
  }

  // 4. Validate any browser-proof cookie locally, then ask the platform for the
  //    full-ladder verdict (A2). detect_full runs server-side: empty-UA, named
  //    bots, search allowlist, fetcher list, browser-proof short-circuit,
  //    Mozilla/ header-scoring, unknown-non-browser. Fails OPEN if unreachable.
  const ua = h.get("User-Agent") || "";
  const secret = env.XENARCH_BROWSER_PROOF_SECRET || "";
  let proofValid = false;
  if (secret) {
    const cookieVal = parseCookie(h.get("Cookie") || "", PROOF_COOKIE);
    if (cookieVal) proofValid = await validateProof(cookieVal, ua, secret);
  }

  const detectHeaders = {};
  for (const name of FORWARD_HEADERS) {
    const v = h.get(name);
    if (v != null) detectHeaders[name] = v.slice(0, MAX_HEADER_VALUE_LEN);
  }

  const verdict = await gateDecide(
    apiBase, siteToken, ua.slice(0, MAX_UA_LEN), detectHeaders, proofValid,
    env.XENARCH_PUBLISHER_ID,
  );

  // Report the detection back (fire-and-forget) for every known-signature bot —
  // ua_match + fetcher_ua — so the dashboard Bots panel + per-site counters
  // reflect edge traffic exactly like WordPress. Header-scored ("Unknown Bot")
  // requests carry no signature and are NOT reported, matching WP log_bot_detection.
  if (verdict.signature) {
    waitUntil(reportDetection(apiBase, siteToken, verdict));
  }

  // Unknown non-browser traffic (webhooks, link-checkers, etc.) → optionally
  // allowed through via a deploy-time toggle. Mirror of WP
  // xenarch_gate_unknown_traffic (default '1' = gate).
  if (
    verdict.method === "unknown_non_browser" &&
    (env.XENARCH_GATE_UNKNOWN_TRAFFIC || "1") !== "1"
  ) {
    return passthrough(request);
  }

  // Allowed (valid browser proof, social/search allowlist, ungated category) → serve.
  if (verdict.decision === "allow") {
    return passthrough(request);
  }

  // Challenge: suspicious Mozilla/ below the block threshold → issue a
  // browser-proof cookie + JS interstitial (403). Real browsers set the cookie
  // and reload into an allow; header-spoofing bots that don't run JS stay out.
  // Mirror of WP render_browser_challenge. Degrade to allow when no secret is
  // configured — a stateless Worker can't issue a verifiable cookie, and an
  // unverifiable one would loop the browser forever.
  if (verdict.decision === "challenge") {
    if (!secret) return passthrough(request);
    const cookieVal = await issueProof(ua, secret);
    return new Response(buildChallengeHtml(path, cookieVal), {
      status: 403,
      headers: {
        "Content-Type": "text/html; charset=utf-8",
        "Cache-Control": "no-store, private",
        "X-Xenarch-Bot": verdict.method || "browser_headers",
        "X-Xenarch-Decision": "challenge",
      },
    });
  }

  // Don't charge for content that doesn't exist (mirror of WP is_404). A quick
  // HEAD to the origin — on the bot-charge path ONLY, never a human's, so it
  // adds no latency to real page loads. A missing page is served as the origin's
  // real 404/410 instead of a nonsense 402 "pay for a page that isn't there".
  if (!(await originExists(request, passthrough, path))) {
    return passthrough(request);
  }

  // 4. Charge: return the platform's 402 superset envelope. The gate's
  //    detection_method carries the WP-style agent label (the signature, or
  //    "Unknown Bot" for header-scored) so the earnings "Agent" column matches.
  const gate = await getOrCreateGate(
    apiBase, siteToken, path, host, verdict.label || "ua_match",
  );
  const body = enrichGatePayload(gate && gate.gate_id ? gate : fallbackGate(path), host);
  return new Response(JSON.stringify(body), {
    status: 402,
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "Cache-Control": "no-store, private",
      "X-Xenarch-Bot": verdict.method || "ua_match",
      "X-Xenarch-Decision": "block",
      ...discoveryHeaders(host),
    },
  });
}

/**
 * POST /v1/gates/{id}/verify {tx_hash}. Returns true if the request may pass.
 * Fail-open policy shared with the other surfaces:
 *   - transport error or 5xx → OPEN (true)
 *   - 4xx                    → CLOSED (false)
 *   - 2xx                    → status === "paid" (or verified/valid)
 */
async function verifyCanonical(apiBase, siteToken, gateId, txHash) {
  let res;
  try {
    res = await fetch(
      `${apiBase}/v1/gates/${encodeURIComponent(gateId)}/verify`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-Site-Token": siteToken },
        body: JSON.stringify({ tx_hash: txHash }),
      },
    );
  } catch (_e) {
    return true; // transport error → fail open
  }
  if (res.status >= 400 && res.status < 500) return false; // deliberate reject
  if (!res.ok) return true; // 5xx → fail open
  let data = {};
  try {
    data = await res.json();
  } catch (_e) {
    return false;
  }
  const status = String(data.status || "").toLowerCase();
  return status === "paid" || !!data.verified || !!data.valid;
}

/** POST /v1/gates/{id}/settle-x402 {x_payment}. True only on a 2xx. */
async function settleX402(apiBase, siteToken, gateId, xPayment) {
  try {
    const res = await fetch(
      `${apiBase}/v1/gates/${encodeURIComponent(gateId)}/settle-x402`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-Site-Token": siteToken },
        body: JSON.stringify({ x_payment: xPayment }),
      },
    );
    return res.ok;
  } catch (_e) {
    return false;
  }
}

/**
 * POST /v1/gate-decide {ua, headers, proof} with X-Site-Token (A2). The platform
 * runs the full WP detection ladder and returns
 * {decision: allow|charge|challenge, signature, category, company, label}.
 * Any platform error fails OPEN (decision "allow", no signature).
 *
 * `headers` is the forwarded subset used for Mozilla/ header-scoring; `proof`
 * is true when this Worker validated a browser-proof cookie. Both feed the
 * scoring / browser-proof steps, so the per-isolate cache key includes a
 * fingerprint of them (same UA can score differently with different headers).
 *
 * `publisherId` (optional, env.XENARCH_PUBLISHER_ID) is sent as a query-param
 * fallback; the site token takes precedence. publisher_id is not secret.
 */
async function gateDecide(apiBase, siteToken, ua, headers, proof, publisherId) {
  const fp = `${JSON.stringify(headers || {})}|${proof ? "p" : ""}`;
  const key = `decide:${ua}|${fp}`;
  const cached = cacheGet(key);
  if (cached) return cached;
  const open = { decision: "allow", signature: null, category: "", company: "", label: "", method: "" };
  try {
    let u = `${apiBase}/v1/gate-decide`;
    if (publisherId) u += `?publisher_id=${encodeURIComponent(publisherId)}`;
    const res = await fetch(u, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Site-Token": siteToken },
      body: JSON.stringify({ ua, headers: headers || {}, proof: !!proof }),
    });
    if (!res.ok) return open;
    const d = await res.json();
    const decision =
      d.decision === "charge" ? "charge" : d.decision === "challenge" ? "challenge" : "allow";
    const verdict = {
      decision,
      signature: d.signature || null,
      category: d.category || "",
      company: d.company || "",
      label: d.label || d.signature || "",
      method: d.method || "",
    };
    cacheSet(key, verdict, DECIDE_TTL_MS);
    return verdict;
  } catch (_e) {
    return open;
  }
}

/**
 * POST /v1/sites/me/bot-detections — report one detected bot. Fire-and-forget
 * (mirrors WP: timeout-0.01, non-blocking). Each call is +1 hit_count, upserted
 * on (site_id, signature) by the platform. Schedule via ctx.waitUntil so it
 * completes after the response is returned.
 */
async function reportDetection(apiBase, siteToken, verdict) {
  try {
    await fetch(`${apiBase}/v1/sites/me/bot-detections`, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Site-Token": siteToken },
      body: JSON.stringify({
        detections: [
          {
            signature: verdict.signature,
            category: verdict.category || "",
            company: verdict.company || "",
          },
        ],
      }),
    });
  } catch (_e) {
    // fire-and-forget — observability must never affect gating
  }
}

/**
 * POST /v1/gates {url, detection_method}. Sends `Origin: https://<host>` so the
 * platform backfills the site's domain on first contact (it 409s a domain-less
 * site otherwise) and builds the correct resource URL. The platform returns the
 * x402 envelope on both 200 and 402. Cached per path; TTL capped by the gate's
 * own `expires`.
 */
async function getOrCreateGate(apiBase, siteToken, path, host, detectionMethod) {
  const key = `gate:${path}`;
  const cached = cacheGet(key);
  if (cached) return cached;
  try {
    const res = await fetch(`${apiBase}/v1/gates`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Site-Token": siteToken,
        Origin: `https://${host}`,
      },
      body: JSON.stringify({ url: path, detection_method: detectionMethod }),
    });
    if (!res.ok && res.status !== 402) return null;
    const data = await res.json();
    if (data && data.gate_id) {
      cacheSet(key, data, gateTtl(data));
      return data;
    }
    return data || null;
  } catch (_e) {
    return null;
  }
}

/** Cap the per-path gate cache at the gate's remaining validity, ≤ 30 min. */
function gateTtl(gate) {
  if (gate && gate.expires) {
    const remaining = Date.parse(gate.expires) - Date.now();
    if (Number.isFinite(remaining) && remaining > 0) {
      return Math.min(GATE_TTL_MS, remaining);
    }
  }
  return GATE_TTL_MS;
}

/**
 * Does the origin actually have this path? A HEAD request, run ONLY on the
 * bot-charge path (never a human's), so it costs no latency on real page loads.
 * Only an explicit 404/410 counts as "missing" → we skip charging and let the
 * origin serve its real not-found (mirror of WP is_404). Any other status (200,
 * 3xx, 405 when HEAD is unsupported, 5xx) or a transport error assumes the page
 * exists, so a hiccup never silently un-gates content. Cached per path to keep a
 * bot hammering dead URLs from re-probing the origin every time.
 */
async function originExists(request, passthrough, path) {
  const key = `exists:${path}`;
  const cached = cacheGet(key);
  if (cached !== undefined) return cached;
  // Probe with a minimal header set, not the bot's full headers — a forwarded
  // Range/If-None-Match/Cookie could turn the status into a conditional artifact
  // (206/304/401) rather than the resource's true existence.
  const probeHeaders = new Headers({ Accept: "*/*" });
  const ua = request.headers.get("User-Agent");
  if (ua) probeHeaders.set("User-Agent", ua);

  let exists = true;
  try {
    let resp = await passthrough(
      new Request(request.url, { method: "HEAD", headers: probeHeaders }),
    );
    // Some origins / asset servers don't implement HEAD — fall back to GET to
    // get a real status (the body is discarded; this is the bot-charge path).
    if (resp.status === 405 || resp.status === 501) {
      resp = await passthrough(
        new Request(request.url, { method: "GET", headers: probeHeaders }),
      );
    }
    if (resp.status === 404 || resp.status === 410) exists = false;
  } catch (_e) {
    exists = true; // can't verify → charge (don't leak content on an origin hiccup)
  }
  cacheSet(key, exists, EXISTS_TTL_MS);
  return exists;
}

/** Minimal 402 body when the platform can't mint a gate. */
function fallbackGate(path) {
  return {
    xenarch: true,
    error: "gate_unavailable",
    protocol: "x402",
    resource: path,
  };
}

// --- Site detail + pricing (free-path parity, pay.json) ---------------------

// Best-effort last-known-good site detail for the platform-outage case. Isolate-
// scoped (not cross-isolate persistent — a cold isolate during an outage serves
// a wallet-less pay.json, the WP last_good option's edge analog would be KV).
let lastGoodSite = null;

/**
 * GET /v1/sites/me (X-Site-Token) → {domain, default_price_usd, payout_wallet,
 * rules:[{path, price_usd}]}. Cached 60s; returns last-known-good on error.
 */
async function getSiteDetail(apiBase, siteToken) {
  const cached = cacheGet("site:detail");
  if (cached) return cached;
  try {
    const res = await fetch(`${apiBase}/v1/sites/me`, {
      headers: { "X-Site-Token": siteToken },
    });
    if (!res.ok) return lastGoodSite;
    const data = await res.json();
    if (data && typeof data === "object") {
      lastGoodSite = data;
      cacheSet("site:detail", data, SITE_TTL_MS);
      return data;
    }
    return lastGoodSite;
  } catch (_e) {
    return lastGoodSite;
  }
}

/**
 * Match a path against a Python-fnmatch glob WITHOUT building a regex — a linear
 * two-pointer matcher, so a pathological pattern (e.g. `*a*a*a*b`) can't trigger
 * catastrophic backtracking on the gating hot path. Supports `*` (any run,
 * including `/`), `?` (one char), and `[seq]`/`[!seq]` classes, matching CPython
 * fnmatch on the full string (case-sensitive, as the platform's resolve_price
 * runs on Linux) so the edge stays in lockstep with the gate the platform mints.
 */
function globMatch(pattern, str) {
  let p = 0;
  let s = 0;
  let starP = -1;
  let starS = -1;
  while (s < str.length) {
    const pc = pattern[p];
    if (p < pattern.length && pc === "*") {
      starP = p++;
      starS = s;
    } else if (p < pattern.length && pc === "?") {
      p++;
      s++;
    } else if (p < pattern.length && pc === "[") {
      const m = matchCharClass(pattern, p, str[s]);
      if (m.matched) {
        p = m.next;
        s++;
      } else if (starP >= 0) {
        p = starP + 1;
        s = ++starS;
      } else {
        return false;
      }
    } else if (p < pattern.length && pc === str[s]) {
      p++;
      s++;
    } else if (starP >= 0) {
      p = starP + 1;
      s = ++starS;
    } else {
      return false;
    }
  }
  while (p < pattern.length && pattern[p] === "*") p++;
  return p === pattern.length;
}

/**
 * Evaluate a `[...]` character class in `pattern` at index `p` against char
 * `ch`. Returns {matched, next} (next = index past `]`). An unterminated class
 * makes `[` a literal, matching fnmatch.
 */
function matchCharClass(pattern, p, ch) {
  let i = p + 1;
  let negate = false;
  if (pattern[i] === "!") {
    negate = true;
    i++;
  }
  let j = i;
  if (pattern[j] === "]") j++; // a leading ] is a literal member
  while (j < pattern.length && pattern[j] !== "]") j++;
  if (j >= pattern.length) {
    return { matched: ch === "[", next: p + 1 }; // unterminated → literal '['
  }
  let matched = false;
  let k = i;
  while (k < j) {
    if (pattern[k + 1] === "-" && k + 2 < j) {
      if (ch >= pattern[k] && ch <= pattern[k + 2]) matched = true;
      k += 3;
    } else {
      if (ch === pattern[k]) matched = true;
      k += 1;
    }
  }
  return { matched: negate ? !matched : matched, next: j + 1 };
}

/**
 * True when an explicit price RULE matches this path with price 0 — a FREE path.
 * Faithful mirror of WP is_free_path (class-xenarch-gate.php:710): only a
 * matching rule frees a path; the site DEFAULT price is NOT considered here (a
 * $0 default must not silently free the whole site). Rule selection uses the
 * platform's fnmatch / longest-pattern-wins so the edge agrees with the gate the
 * platform would mint.
 */
function isFreeByRule(path, site) {
  const rules = Array.isArray(site && site.rules) ? site.rules : [];
  let best = null;
  let bestLen = -1;
  for (const r of rules) {
    if (!r || !r.path) continue;
    const pat = String(r.path);
    // Longest matching pattern wins, first on ties — exactly mirrors the
    // platform resolve_price (`len(rule.path_pattern) > best_length`).
    if (globMatch(pat, path) && pat.length > bestLen) {
      best = r;
      bestLen = pat.length;
    }
  }
  return best !== null && Number(best.price_usd) === 0;
}

// --- Discovery (mirror of WP get_discovery_headers / enrich_gate_payload) ----

/** Keep only valid hostname characters before interpolating into headers/docs.
 * Cloudflare already rejects malformed Host before the isolate runs; this makes
 * the code correct even off-CF (no CRLF/quote injection into Link headers). */
function safeHost(host) {
  return String(host || "").replace(/[^a-zA-Z0-9.:[\]-]/g, "");
}

/** The three discovery HTTP headers WP adds to every 402 block response. */
function discoveryHeaders(host) {
  host = safeHost(host);
  const payJson = `https://${host}/.well-known/pay.json`;
  const xenarchMd = `https://${host}/.well-known/xenarch.md`;
  return {
    Link: `<${payJson}>; rel="payment-terms", <${xenarchMd}>; rel="payment-instructions"`,
    "X-Pay-Json": payJson,
    "X-Xenarch": `payment-required; pay_json="${payJson}"`,
  };
}

/** Add pay_json_url / instructions_url / message to a 402 body (WP enrich). */
function enrichGatePayload(gate, host) {
  host = safeHost(host);
  const payJson = `https://${host}/.well-known/pay.json`;
  const xenarchMd = `https://${host}/.well-known/xenarch.md`;
  if (gate.xenarch === undefined) gate.xenarch = true;
  gate.pay_json_url = payJson;
  gate.instructions_url = xenarchMd;
  gate.message =
    `Payment required. Fetch ${payJson} for pricing, payment address, and ` +
    `integration tools. Full instructions at ${xenarchMd}`;
  return gate;
}

/** Serve /.well-known/pay.json (pay-json v1.2) generated from platform detail. */
async function servePayJson(apiBase, siteToken, host, passthrough, request) {
  const site = await getSiteDetail(apiBase, siteToken);
  // No platform data (and no last-known-good) → don't fabricate a price; let the
  // origin serve its own pay.json (or 404). Price lives only on the platform.
  if (!site || site.default_price_usd == null) return passthrough(request);
  const wallet = site.payout_wallet || "";
  const price = String(site.default_price_usd);
  const rules = [];
  if (Array.isArray(site.rules)) {
    for (const r of site.rules) {
      if (r && r.path && r.price_usd != null) {
        rules.push({ path: String(r.path), price_usd: String(r.price_usd) });
      }
    }
  }
  rules.push({ path: "/**", price_usd: price });
  const payJson = {
    version: "1.2",
    protocol: "x402",
    network: "base",
    asset: "USDC",
    receiver: wallet,
    seller_wallet: wallet,
    facilitators: FACILITATORS,
    rules,
  };
  return new Response(JSON.stringify(payJson, null, 2), {
    status: 200,
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "Cache-Control": "public, max-age=3600",
    },
  });
}

/** Serve /.well-known/xenarch.md — the human/LLM payment guide (WP mirror). */
async function serveXenarchMd(apiBase, siteToken, host, passthrough, request) {
  const site = await getSiteDetail(apiBase, siteToken);
  if (!site || site.default_price_usd == null) return passthrough(request);
  const domain = safeHost(host);
  const siteUrl = `https://${domain}`;
  const price = String(site.default_price_usd);
  const md =
`# Xenarch Payment Gate — ${domain}

## What's Happening

This site charges AI agents for content access using the x402 micropayment protocol. You received an HTTP 402 response because your request was identified as coming from an AI agent.

This is not an error. It means the content is available for purchase.

## How to Pay (x402 standard flow)

### Step 1: Discover the gate

Make a request to any page. If you are detected as an AI agent, you'll receive HTTP 402 with a JSON body and discovery headers pointing at \`pay.json\`:

\`\`\`
GET ${siteUrl}/your-page
→ HTTP/1.1 402 Payment Required
→ Link: <${siteUrl}/.well-known/pay.json>; rel="payment-terms"
\`\`\`

Fetch \`${siteUrl}/.well-known/pay.json\` to learn the price, the seller wallet, and the ranked list of x402 facilitators that can settle the payment.

### Step 2: Pay via an x402 facilitator

Pick a facilitator from the \`facilitators\` array. Sign a USDC \`transferWithAuthorization\` (EIP-3009) for the price, payable to \`seller_wallet\` on Base, and submit it to the facilitator. The facilitator broadcasts the transaction and returns the on-chain \`tx_hash\`.

Funds settle directly from your wallet to the publisher wallet. Xenarch is never in the money flow — there is no intermediary contract.

### Step 3: Replay the request with proof of payment

Send both the gate UUID (from the 402 response body) and the on-chain transaction hash:

\`\`\`
GET ${siteUrl}/your-page
X-Xenarch-Gate-Id: <gate_id from the 402 body>
X-Xenarch-Tx-Hash: 0x<your_transaction_hash>
\`\`\`

The edge re-verifies the transaction against the platform (\`/v1/gates/<gate_id>/verify\`). If the on-chain payment matches the gate price and recipient, you receive the full page with HTTP 200.

## Easiest path: use the Xenarch MCP server

If you are an AI agent (Claude, Cursor, LangChain, CrewAI, or any MCP client), install the Xenarch MCP server and the three steps above happen automatically:

\`\`\`bash
npx @xenarch/agent-mcp
\`\`\`

Then ask the agent to fetch the URL. It will discover the gate, pick a facilitator, sign the payment, and return the content.

## Pricing

- Default price: $${price} per page
- Full pricing rules: ${siteUrl}/.well-known/pay.json
- Maximum price: $1.00 (protocol limit)

## Links

- Xenarch documentation: https://xenarch.com/docs
- x402 spec: https://www.x402.org/
- pay.json standard: https://payjson.org
- pay.json for this site: ${siteUrl}/.well-known/pay.json
`;
  return new Response(md, {
    status: 200,
    headers: {
      "Content-Type": "text/markdown; charset=utf-8",
      "Cache-Control": "public, max-age=3600",
    },
  });
}

// --- Browser proof (mirror of WP class-xenarch-browser-proof.php) -----------

/** Read one cookie value by name from a raw Cookie header. '' when absent. */
function parseCookie(cookieHeader, name) {
  if (!cookieHeader) return "";
  for (const part of cookieHeader.split(";")) {
    const eq = part.indexOf("=");
    if (eq < 0) continue;
    if (part.slice(0, eq).trim() === name) return part.slice(eq + 1).trim();
  }
  return "";
}

/** hex(HMAC-SHA256(secret, msg)) — matches PHP hash_hmac('sha256', …) default. */
async function hmacHex(secret, msg) {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw", enc.encode(secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign"],
  );
  const sig = await crypto.subtle.sign("HMAC", key, enc.encode(msg));
  return [...new Uint8Array(sig)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

/** Constant-time string compare (avoids leaking the MAC via timing). */
function timingSafeEqual(a, b) {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

/** Issue a signed proof cookie bound to the UA + issue time. */
async function issueProof(ua, secret, issuedAt) {
  const ts = issuedAt || Math.floor(Date.now() / 1000);
  const mac = await hmacHex(secret, `${ts}|${ua}`);
  return btoa(`${ts}|${mac}`);
}

/** Validate a proof cookie: well-formed, unexpired (TTL 900s), MAC matches. */
async function validateProof(cookieValue, ua, secret, now) {
  if (!cookieValue || !ua) return false;
  let decoded;
  try {
    decoded = atob(cookieValue);
  } catch (_e) {
    return false;
  }
  const parts = decoded.split("|");
  if (parts.length !== 2) return false;
  const issuedAt = parseInt(parts[0], 10);
  if (!Number.isFinite(issuedAt) || issuedAt <= 0) return false;
  const cur = now || Math.floor(Date.now() / 1000);
  if (issuedAt + PROOF_TTL < cur) return false;
  const expected = await hmacHex(secret, `${issuedAt}|${ua}`);
  return timingSafeEqual(expected, parts[1]);
}

/** JSON-encode for safe inline-script embedding (mirror of JSON_HEX_TAG|HEX_AMP). */
function jsonForScript(value) {
  return JSON.stringify(String(value))
    .replace(/</g, "\\u003c")
    .replace(/>/g, "\\u003e")
    .replace(/&/g, "\\u0026");
}

/** The browser-proof challenge page (mirror of WP build_challenge_html). */
function buildChallengeHtml(requestPath, cookieValue) {
  // Prevent open redirect: local path only (single leading slash).
  let target = requestPath;
  if (!target || target[0] !== "/" || target.startsWith("//")) target = "/";
  return (
    '<!doctype html><html><head><meta charset="utf-8">' +
    '<meta name="viewport" content="width=device-width, initial-scale=1">' +
    "<title>Verifying browser</title></head><body>" +
    "<noscript>JavaScript is required to continue.</noscript>" +
    "<script>(function(){var cookieName=" + jsonForScript(PROOF_COOKIE) +
    ";var cookieValue=" + jsonForScript(cookieValue) +
    ";var target=" + jsonForScript(target) + ";" +
    'document.cookie=cookieName+"="+cookieValue+"; Path=/; Max-Age=' + PROOF_TTL +
    '; SameSite=Lax";window.location.replace(target);})();</script>' +
    "<p>Verifying browser...</p></body></html>"
  );
}

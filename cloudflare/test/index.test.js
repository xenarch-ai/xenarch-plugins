import { afterEach, describe, expect, it, vi } from "vitest";

import worker from "../src/index.js";
import { __resetGateCaches } from "../src/gate.js";

const ENV = { XENARCH_API_BASE: "https://api.test", XENARCH_SITE_TOKEN: "st_test" };
const ORIGIN = "https://shop.example.com";

function req(path, headers = {}) {
  return new Request(ORIGIN + path, { headers });
}

// Route the Worker's fetch calls: the origin passthrough vs platform API.
// `api` maps an endpoint substring -> { status, body }.
function mockFetch(api) {
  return vi.fn(async (input, init) => {
    const u = typeof input === "string" ? input : input.url;
    if (u.startsWith(ORIGIN)) {
      return new Response("ORIGIN", { status: 200 });
    }
    for (const [needle, resp] of Object.entries(api)) {
      if (u.includes(needle)) {
        return new Response(
          resp.body === undefined ? "" : JSON.stringify(resp.body),
          { status: resp.status ?? 200 },
        );
      }
    }
    throw new Error("unexpected fetch: " + u);
  });
}

afterEach(() => {
  vi.restoreAllMocks();
  __resetGateCaches();
});

const GPTBOT = "Mozilla/5.0 (compatible; GPTBot/1.0)";

describe("config + skip rules", () => {
  it("passes through when no site token configured", async () => {
    global.fetch = mockFetch({});
    const res = await worker.fetch(req("/a"), { XENARCH_API_BASE: "https://api.test" });
    expect(await res.text()).toBe("ORIGIN");
  });

  it("generates pay.json from platform site detail (not origin)", async () => {
    global.fetch = mockFetch({
      "/v1/sites/me": {
        body: {
          domain: "shop.example.com",
          default_price_usd: "0.003",
          payout_wallet: "0xWALLET",
          rules: [{ path: "/premium/*", price_usd: "0.01" }],
        },
      },
    });
    const res = await worker.fetch(req("/.well-known/pay.json"), ENV);
    expect(res.status).toBe(200);
    const pj = await res.json();
    expect(pj.version).toBe("1.2");
    expect(pj.seller_wallet).toBe("0xWALLET");
    expect(pj.rules.at(-1)).toEqual({ path: "/**", price_usd: "0.003" });
    expect(pj.facilitators.length).toBe(3);
  });

  it("passes other .well-known paths through to origin", async () => {
    global.fetch = mockFetch({});
    const res = await worker.fetch(req("/.well-known/acme-challenge/abc"), ENV);
    expect(await res.text()).toBe("ORIGIN");
  });

  it("never gates static assets", async () => {
    global.fetch = mockFetch({});
    const res = await worker.fetch(req("/assets/app.css", { "User-Agent": GPTBOT }), ENV);
    expect(await res.text()).toBe("ORIGIN");
  });
});

describe("gate-decide verdict", () => {
  it("charges a known bot → 402 with the platform envelope", async () => {
    global.fetch = mockFetch({
      "/gate-decide": { body: { decision: "charge", category: "general_ai" } },
      "/v1/gates": { status: 402, body: { gate_id: "g1", xenarch: true, accepts: [] } },
    });
    const res = await worker.fetch(req("/article-charge", { "User-Agent": GPTBOT }), ENV);
    expect(res.status).toBe(402);
    const body = await res.json();
    expect(body.gate_id).toBe("g1");
    expect(res.headers.get("X-Xenarch-Decision")).toBe("block");
  });

  it("allows a search bot → origin", async () => {
    global.fetch = mockFetch({
      "/gate-decide": { body: { decision: "allow" } },
    });
    const res = await worker.fetch(req("/article-allow", { "User-Agent": "PerplexityBot" }), ENV);
    expect(await res.text()).toBe("ORIGIN");
  });

  it("empty UA → charged (WP blocks empty UAs, full-ladder parity)", async () => {
    global.fetch = mockFetch({
      "/gate-decide": { body: { decision: "charge", label: "empty_ua" } },
      "/v1/gates": { status: 402, body: { gate_id: "ge" } },
    });
    const res = await worker.fetch(req("/article-emptyua"), ENV);
    expect(res.status).toBe(402);
  });

  it("HTTP-client fetcher (curl) → charged", async () => {
    global.fetch = mockFetch({
      "/gate-decide": {
        body: { decision: "charge", signature: "curl/", category: "general_ai", label: "curl/" },
      },
      "/v1/gates": { status: 402, body: { gate_id: "gc" } },
    });
    const res = await worker.fetch(req("/doc", { "User-Agent": "curl/8.4.0" }), ENV);
    expect(res.status).toBe(402);
  });

  it("does NOT charge for a nonexistent page (origin 404 → passthrough)", async () => {
    global.fetch = vi.fn(async (input, init) => {
      const u = typeof input === "string" ? input : input.url;
      const method = (typeof input === "string" ? init?.method : input.method) || "GET";
      if (u.includes("/gate-decide"))
        return new Response(JSON.stringify({ decision: "charge", label: "GPTBot", method: "ua_match" }), { status: 200 });
      if (u.includes("/v1/sites/me"))
        return new Response(JSON.stringify({ default_price_usd: "0.001", rules: [] }), { status: 200 });
      if (u.startsWith(ORIGIN))
        return new Response(method === "HEAD" ? null : "NOT FOUND", { status: 404 });
      throw new Error("unexpected: " + u);
    });
    const res = await worker.fetch(req("/ghost-page", { "User-Agent": GPTBOT }), ENV);
    expect(res.status).toBe(404); // origin's real 404, NOT a 402 charge
  });

  it("still charges for a page that exists (origin 200 → 402)", async () => {
    global.fetch = vi.fn(async (input) => {
      const u = typeof input === "string" ? input : input.url;
      if (u.includes("/gate-decide"))
        return new Response(JSON.stringify({ decision: "charge", label: "GPTBot", method: "ua_match" }), { status: 200 });
      if (u.includes("/v1/sites/me"))
        return new Response(JSON.stringify({ default_price_usd: "0.001", rules: [] }), { status: 200 });
      if (u.includes("/v1/gates"))
        return new Response(JSON.stringify({ gate_id: "gr1" }), { status: 402 });
      if (u.startsWith(ORIGIN)) return new Response("REAL PAGE", { status: 200 });
      throw new Error("unexpected: " + u);
    });
    const res = await worker.fetch(req("/real-page", { "User-Agent": GPTBOT }), ENV);
    expect(res.status).toBe(402);
  });

  it("platform outage on gate-decide fails open → origin", async () => {
    global.fetch = mockFetch({
      "/gate-decide": { status: 503 },
    });
    const res = await worker.fetch(req("/article-outage", { "User-Agent": GPTBOT }), ENV);
    expect(await res.text()).toBe("ORIGIN");
  });
});

describe("browser-proof challenge band", () => {
  const SUSPECT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64)";

  it("challenge verdict + secret → 403 JS interstitial", async () => {
    global.fetch = mockFetch({
      "/gate-decide": { body: { decision: "challenge", label: "Unknown Bot" } },
    });
    const res = await worker.fetch(
      req("/dispatch/x", { "User-Agent": SUSPECT }),
      { ...ENV, XENARCH_BROWSER_PROOF_SECRET: "test-secret" },
    );
    expect(res.status).toBe(403);
    expect(res.headers.get("X-Xenarch-Decision")).toBe("challenge");
    const html = await res.text();
    expect(html).toContain("Verifying browser");
    expect(html).toContain("xenarch_browser_proof");
  });

  it("challenge verdict without a configured secret → degrades to origin", async () => {
    global.fetch = mockFetch({
      "/gate-decide": { body: { decision: "challenge", label: "Unknown Bot" } },
    });
    const res = await worker.fetch(req("/dispatch/x", { "User-Agent": SUSPECT }), ENV);
    expect(await res.text()).toBe("ORIGIN");
  });

  it("a request carrying a valid proof cookie is allowed (round-trips the challenge)", async () => {
    const secret = "test-secret";
    const env = { ...ENV, XENARCH_BROWSER_PROOF_SECRET: secret };
    // First: get challenged and capture the issued cookie from the page.
    global.fetch = mockFetch({
      "/gate-decide": { body: { decision: "challenge", label: "Unknown Bot" } },
    });
    const first = await worker.fetch(req("/dispatch/x", { "User-Agent": SUSPECT }), env);
    const html = await first.text();
    const m = html.match(/var cookieValue="([^"]+)"/);
    expect(m).toBeTruthy();
    const cookieValue = m[1];
    // Second: replay with the proof cookie; the platform now allows (proof=true).
    global.fetch = mockFetch({
      "/gate-decide": { body: { decision: "allow" } },
    });
    const second = await worker.fetch(
      req("/dispatch/x", { "User-Agent": SUSPECT, Cookie: `xenarch_browser_proof=${cookieValue}` }),
      env,
    );
    expect(await second.text()).toBe("ORIGIN");
  });
});

describe("WP parity: free paths, discovery, toggles", () => {
  it("free path (price-0 rule) is served to everyone, before detection", async () => {
    global.fetch = mockFetch({
      "/v1/sites/me": {
        body: {
          default_price_usd: "0.003",
          rules: [{ path: "/free/*", price_usd: "0" }],
        },
      },
      // gate-decide must NOT be consulted for a free path; if it is and returns
      // charge, the test would fail — proving the free check runs first.
      "/gate-decide": { body: { decision: "charge", label: "GPTBot" } },
    });
    const res = await worker.fetch(
      req("/free/report", { "User-Agent": "Mozilla/5.0 (compatible; GPTBot/1.0)" }),
      ENV,
    );
    expect(await res.text()).toBe("ORIGIN");
  });

  it("a non-rule path still gates even when site default is $0", async () => {
    // Faithful WP is_free_path: the default price does NOT free a path.
    global.fetch = mockFetch({
      "/v1/sites/me": { body: { default_price_usd: "0", rules: [] } },
      "/gate-decide": { body: { decision: "charge", label: "GPTBot", method: "ua_match" } },
      "/v1/gates": { status: 402, body: { gate_id: "g0" } },
    });
    const res = await worker.fetch(
      req("/article", { "User-Agent": "Mozilla/5.0 (compatible; GPTBot/1.0)" }),
      ENV,
    );
    expect(res.status).toBe(402);
  });

  it("402 carries discovery headers + enriched body + X-Xenarch-Bot", async () => {
    global.fetch = mockFetch({
      "/v1/sites/me": { body: { default_price_usd: "0.003", rules: [] } },
      "/gate-decide": { body: { decision: "charge", signature: "GPTBot", label: "GPTBot", method: "ua_match" } },
      "/v1/gates": { status: 402, body: { gate_id: "g1", xenarch: true, accepts: [] } },
    });
    const res = await worker.fetch(
      req("/article-disc", { "User-Agent": "Mozilla/5.0 (compatible; GPTBot/1.0)" }),
      ENV,
    );
    expect(res.status).toBe(402);
    expect(res.headers.get("X-Xenarch-Bot")).toBe("ua_match");
    expect(res.headers.get("X-Pay-Json")).toContain("/.well-known/pay.json");
    expect(res.headers.get("Link")).toContain('rel="payment-terms"');
    const body = await res.json();
    expect(body.pay_json_url).toContain("/.well-known/pay.json");
    expect(body.instructions_url).toContain("/.well-known/xenarch.md");
    expect(body.message).toContain("Payment required");
  });

  it("unknown-non-browser passes when XENARCH_GATE_UNKNOWN_TRAFFIC=0", async () => {
    global.fetch = mockFetch({
      "/v1/sites/me": { body: { default_price_usd: "0.003", rules: [] } },
      "/gate-decide": { body: { decision: "charge", method: "unknown_non_browser" } },
    });
    const res = await worker.fetch(
      req("/hook", { "User-Agent": "MyToolkit/1.0" }),
      { ...ENV, XENARCH_GATE_UNKNOWN_TRAFFIC: "0" },
    );
    expect(await res.text()).toBe("ORIGIN");
  });
});

describe("bilingual inbound payment", () => {
  it("canonical proof verified → origin", async () => {
    global.fetch = mockFetch({
      "/verify": { body: { status: "paid" } },
    });
    const res = await worker.fetch(
      req("/paid-canonical", {
        "User-Agent": GPTBOT,
        "X-Xenarch-Gate-Id": "g1",
        "X-Xenarch-Tx-Hash": "0xabc",
      }),
      ENV,
    );
    expect(await res.text()).toBe("ORIGIN");
  });

  it("4xx on verify falls through to the gate (no fail-open)", async () => {
    global.fetch = mockFetch({
      "/verify": { status: 400, body: { detail: "bad" } },
      "/gate-decide": { body: { decision: "charge" } },
      "/v1/gates": { status: 402, body: { gate_id: "g9" } },
    });
    const res = await worker.fetch(
      req("/paid-bad", {
        "User-Agent": GPTBOT,
        "X-Xenarch-Gate-Id": "g1",
        "X-Xenarch-Tx-Hash": "0xbad",
      }),
      ENV,
    );
    expect(res.status).toBe(402);
  });

  it("vanilla x402 voucher settled → origin", async () => {
    global.fetch = mockFetch({
      "/v1/gates/": { body: { ok: true } }, // settle-x402 (matched first by substring)
      "/v1/gates": { status: 402, body: { gate_id: "gx" } }, // create gate
    });
    // The create-gate call must resolve before settle; order the mock by
    // returning the gate envelope for the bare /v1/gates POST and ok for settle.
    global.fetch = vi.fn(async (input) => {
      const u = typeof input === "string" ? input : input.url;
      if (u.startsWith(ORIGIN)) return new Response("ORIGIN", { status: 200 });
      if (u.endsWith("/settle-x402")) return new Response(JSON.stringify({ ok: true }), { status: 200 });
      if (u.endsWith("/v1/gates")) return new Response(JSON.stringify({ gate_id: "gx" }), { status: 402 });
      throw new Error("unexpected: " + u);
    });
    const res = await worker.fetch(
      req("/paid-x402", { "User-Agent": GPTBOT, "X-Payment": "dmF1Y2hlcg==" }),
      ENV,
    );
    expect(await res.text()).toBe("ORIGIN");
  });
});

import { afterEach, describe, expect, it, vi } from "vitest";

import worker from "../src/index.js";

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
});

const GPTBOT = "Mozilla/5.0 (compatible; GPTBot/1.0)";

describe("config + skip rules", () => {
  it("passes through when no site token configured", async () => {
    global.fetch = mockFetch({});
    const res = await worker.fetch(req("/a"), { XENARCH_API_BASE: "https://api.test" });
    expect(await res.text()).toBe("ORIGIN");
  });

  it("never gates /.well-known discovery", async () => {
    global.fetch = mockFetch({});
    const res = await worker.fetch(req("/.well-known/pay.json"), ENV);
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

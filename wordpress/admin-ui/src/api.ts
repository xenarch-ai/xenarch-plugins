import type { Settings } from './types'

// All admin-side traffic to platform goes through the plugin's own REST
// proxy (server-to-server). The React app never talks to api.xenarch.dev
// directly — that would be cross-origin against an allow-list that
// doesn't include merchant domains, and would also require the merchant
// to set up the SIWE cookie scope manually.

function getConfig() {
  return window.xenarchAdmin
}

async function apiFetch<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const { restUrl, nonce } = getConfig()
  const res = await fetch(`${restUrl}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...options.headers,
    },
  })

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new Error(data.detail || data.error || data.message || `Request failed (${res.status})`)
  }

  return res.json()
}

export function fetchSettings(): Promise<Settings> {
  return apiFetch<Settings>('/settings')
}

// XEN-380: finish the claim handshake. The merchant clicked "Connect"
// in the plugin admin, was redirected to dash.xenarch.dev/sites/claim
// where they confirmed pairing this domain with their Xenarch identity,
// and was bounced back here with ``?claim_token=…`` in the URL. We POST
// the token to our own REST proxy, which calls the platform server-side
// for the long-lived site_token.
export function exchangeClaim(claimToken: string): Promise<Settings> {
  return apiFetch<Settings>('/claim-exchange', {
    method: 'POST',
    body: JSON.stringify({ claim_token: claimToken }),
  })
}

export function disconnect(): Promise<Settings> {
  return apiFetch<Settings>('/disconnect', { method: 'POST' })
}

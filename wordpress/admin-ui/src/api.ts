import type { Settings, PricingRule, StatsResponse, TransactionsResponse } from './types'

function getConfig() {
  return window.xenarchAdmin
}

async function apiFetch<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const { restUrl, nonce } = getConfig()
  const url = `${restUrl}${path}`

  const res = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...options.headers,
    },
  })

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new Error(data.error || data.message || `Request failed (${res.status})`)
  }

  return res.json()
}

// Settings
export function fetchSettings(): Promise<Settings> {
  return apiFetch<Settings>('/settings')
}

export function updateSettings(data: Record<string, string>): Promise<Settings> {
  return apiFetch<Settings>('/settings', {
    method: 'POST',
    body: JSON.stringify(data),
  })
}

// Registration
export function register(email: string, password: string): Promise<Settings> {
  return apiFetch<Settings>('/register', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })
}

// Add site
export function addSite(): Promise<Settings> {
  return apiFetch<Settings>('/add-site', { method: 'POST' })
}

// Pricing rules
export function fetchPricingRules(): Promise<{ rules: PricingRule[] }> {
  return apiFetch<{ rules: PricingRule[] }>('/pricing-rules')
}

export function savePricingRules(rules: PricingRule[]): Promise<{ rules: PricingRule[] }> {
  return apiFetch<{ rules: PricingRule[] }>('/pricing-rules', {
    method: 'PUT',
    body: JSON.stringify({ rules }),
  })
}

// Stats (proxied from platform)
export function fetchStats(): Promise<StatsResponse> {
  return apiFetch<StatsResponse>('/stats')
}

// Transactions (proxied from platform)
export function fetchTransactions(
  period: string = 'all',
  page: number = 1,
  perPage: number = 25,
  status: string = 'paid'
): Promise<TransactionsResponse> {
  const params = new URLSearchParams({
    period,
    page: String(page),
    per_page: String(perPage),
    status,
  })
  return apiFetch<TransactionsResponse>(`/transactions?${params}`)
}

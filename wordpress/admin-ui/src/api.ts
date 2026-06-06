import type {
  Settings,
  SiteDetail,
  SiteStats,
  TransactionsResponse,
  CategoryBreakdownResponse,
  GatedCategories,
  PricingRule,
} from './types'

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

// Plugin-local
export function fetchSettings(): Promise<Settings> {
  return apiFetch<Settings>('/settings')
}

export function exchangeClaim(claimToken: string): Promise<Settings> {
  return apiFetch<Settings>('/claim-exchange', {
    method: 'POST',
    body: JSON.stringify({ claim_token: claimToken }),
  })
}

export function disconnect(): Promise<Settings> {
  return apiFetch<Settings>('/disconnect', { method: 'POST' })
}

// XEN-380/383 — platform-mirrored reads/writes via /xenarch/v1/site/*
export function fetchSite(): Promise<SiteDetail> {
  return apiFetch<SiteDetail>('/site')
}

export function fetchStats(): Promise<SiteStats> {
  return apiFetch<SiteStats>('/site/stats')
}

export function fetchTransactions(
  period: '24h' | '7d' | '30d' | 'all' = 'all',
  page = 1,
  per_page = 25,
  status: 'paid' | 'blocked' | 'withdraw' | 'all' = 'all',
): Promise<TransactionsResponse> {
  const qs = new URLSearchParams({
    period,
    page: String(page),
    per_page: String(per_page),
    status,
  })
  return apiFetch<TransactionsResponse>(`/site/transactions?${qs}`)
}

export function fetchCategoryBreakdown(): Promise<CategoryBreakdownResponse> {
  return apiFetch<CategoryBreakdownResponse>('/site/category-breakdown')
}

// XEN-435 P4 — linked-wallet payout selection.
export interface SiteWallet {
  address: string
  eligible_at: string
  eligible: boolean // cooldown elapsed → can receive
  is_default: boolean // the gate's current receiving wallet
}

export function fetchSiteWallets(): Promise<{ wallets: SiteWallet[] }> {
  return apiFetch<{ wallets: SiteWallet[] }>('/site/wallets')
}

export function setPayoutWallet(wallet: string): Promise<{ payout_wallet: string }> {
  return apiFetch<{ payout_wallet: string }>('/site/payout-wallet', {
    method: 'PUT',
    body: JSON.stringify({ wallet }),
  })
}

export interface GatingUpdate {
  gating_enabled: boolean
  gated_categories: GatedCategories
  use_publisher_defaults: boolean
}

export function putGating(body: GatingUpdate): Promise<GatingUpdate> {
  return apiFetch<GatingUpdate>('/site/gating', {
    method: 'PUT',
    body: JSON.stringify(body),
  })
}

export interface PricingUpdate {
  default_price_usd: number
  default_billing_scope: 'page' | 'path'
  rules: PricingRule[]
}

export function putPricing(body: PricingUpdate): Promise<{ rules_applied: number }> {
  return apiFetch<{ rules_applied: number }>('/site/pricing', {
    method: 'PUT',
    body: JSON.stringify(body),
  })
}

import type {
  Settings,
  SiteDetail,
  SiteStats,
  TransactionsResponse,
  CategoryBreakdownResponse,
  GatedCategories,
  PricingRule,
} from './types'

// Joomla transport. Same exported surface as the WordPress plugin's api.ts —
// the React components are shared 1:1 — but routed through the admin
// component's AjaxController instead of WP's REST namespace:
//
//   POST {restUrl}&task=ajax.<endpoint>&method=<METHOD>&<formToken>=1
//
// `restUrl` is administrator/index.php?option=com_xenarch&format=json and
// `nonce` is the Joomla session form token (its *name*); Joomla's
// Session::checkToken('request') wants that token name present with value 1.

function getConfig() {
  return window.xenarchAdmin
}

async function apiFetch<T>(
  endpoint: string,
  options: { method?: 'GET' | 'POST' | 'PUT'; body?: string; query?: string } = {}
): Promise<T> {
  const { restUrl, nonce } = getConfig()
  const method = options.method ?? 'GET'

  let url = `${restUrl}&task=ajax.${endpoint}&method=${method}&${nonce}=1`
  if (options.query) {
    url += `&${options.query}`
  }

  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: options.body,
  })

  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    throw new Error(data.detail || data.error || data.message || `Request failed (${res.status})`)
  }

  return res.json()
}

// Plugin-local
export function fetchSettings(): Promise<Settings> {
  return apiFetch<Settings>('settings')
}

export function exchangeClaim(claimToken: string): Promise<Settings> {
  return apiFetch<Settings>('claimexchange', {
    method: 'POST',
    body: JSON.stringify({ claim_token: claimToken }),
  })
}

export function disconnect(): Promise<Settings> {
  return apiFetch<Settings>('disconnect', { method: 'POST' })
}

// Platform-mirrored reads/writes via the AjaxController site_token proxies.
export function fetchSite(): Promise<SiteDetail> {
  return apiFetch<SiteDetail>('site')
}

export function fetchStats(): Promise<SiteStats> {
  return apiFetch<SiteStats>('stats')
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
  return apiFetch<TransactionsResponse>('transactions', { query: qs.toString() })
}

export function fetchCategoryBreakdown(): Promise<CategoryBreakdownResponse> {
  return apiFetch<CategoryBreakdownResponse>('categorybreakdown')
}

// XEN-435 P4 — linked-wallet payout selection.
export interface SiteWallet {
  address: string
  eligible_at: string
  eligible: boolean // cooldown elapsed → can receive
  is_default: boolean // the gate's current receiving wallet
}

export function fetchSiteWallets(): Promise<{ wallets: SiteWallet[] }> {
  return apiFetch<{ wallets: SiteWallet[] }>('wallets')
}

export function setPayoutWallet(wallet: string): Promise<{ payout_wallet: string }> {
  return apiFetch<{ payout_wallet: string }>('payoutwallet', {
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
  return apiFetch<GatingUpdate>('gating', {
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
  return apiFetch<{ rules_applied: number }>('pricing', {
    method: 'PUT',
    body: JSON.stringify(body),
  })
}

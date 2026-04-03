import type { Settings, PricingRule, StatsResponse, TransactionsResponse, BotCategories, BotOverrides, BotSignature, BotAction, PagePath, CategoryBreakdownItem, WalletBalanceResponse, SellOptions, SellQuote } from './types'

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

// Bot categories
export function fetchBotCategories(): Promise<BotCategories> {
  return apiFetch<BotCategories>('/bot-categories')
}

export function updateBotCategories(data: Partial<BotCategories>): Promise<BotCategories> {
  return apiFetch<BotCategories>('/bot-categories', {
    method: 'POST',
    body: JSON.stringify(data),
  })
}

// Bot overrides
export function fetchBotOverrides(): Promise<BotOverrides> {
  return apiFetch<BotOverrides>('/bot-overrides')
}

export function updateBotOverrides(data: Record<string, BotAction | null>): Promise<BotOverrides> {
  return apiFetch<BotOverrides>('/bot-overrides', {
    method: 'POST',
    body: JSON.stringify(data),
  })
}

// Bot signatures
export function fetchBotSignatures(): Promise<{ signatures: BotSignature[] }> {
  return apiFetch<{ signatures: BotSignature[] }>('/bot-signatures')
}

// Page paths autocomplete
export function searchPagePaths(query: string): Promise<{ paths: PagePath[] }> {
  return apiFetch<{ paths: PagePath[] }>(`/page-paths?q=${encodeURIComponent(query)}`)
}

// Stats (proxied from platform)
export function fetchStats(): Promise<StatsResponse> {
  return apiFetch<StatsResponse>('/stats')
}

// Wallet balance (Xenarch wallets only)
export function fetchBalance(): Promise<WalletBalanceResponse> {
  return apiFetch<WalletBalanceResponse>('/balance')
}

// Category breakdown
export function fetchCategoryBreakdown(): Promise<{ categories: CategoryBreakdownItem[] }> {
  return apiFetch<{ categories: CategoryBreakdownItem[] }>('/category-breakdown')
}

// Offramp — sell options (available methods and limits)
export function fetchSellOptions(country: string): Promise<SellOptions> {
  return apiFetch<SellOptions>(`/sell-options?country=${encodeURIComponent(country)}`)
}

// Offramp — create sell quote (returns Coinbase offramp URL)
export function createSellQuote(amountUsd: string, country: string): Promise<SellQuote> {
  return apiFetch<SellQuote>('/sell-quote', {
    method: 'POST',
    body: JSON.stringify({ amount_usd: amountUsd, country }),
  })
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

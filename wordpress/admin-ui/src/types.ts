// XEN-380: the plugin is a thin window into the platform. The local
// snapshot below is everything that lives in WP MySQL — the rest comes
// live from /xenarch/v1/site/* (proxied to platform).

export interface XenarchAdmin {
  restUrl: string
  nonce: string
  settings: Settings
  pluginUrl: string
  version: string
}

export interface Settings {
  site_id: string
  site_token: string
  domain: string
  has_site: boolean
  bot_signature_count: number
  pay_json_url: string
  xenarch_md_url: string
}

declare global {
  interface Window {
    xenarchAdmin: XenarchAdmin
  }
}

// ---------------------------------------------------------------------------
// Platform-mirrored shapes (XEN-383). Same JSON the dashboard reads at
// /v1/me/sites/{id}/*; here we get them through /xenarch/v1/site/* which
// proxies via X-Site-Token so the browser stays same-origin.
// ---------------------------------------------------------------------------

export type BotCategoryKey =
  | 'ai_search'
  | 'ai_assistants'
  | 'ai_agents'
  | 'ai_training'
  | 'scrapers'
  | 'general_ai'

export type GatedCategories = Record<BotCategoryKey, boolean>

export interface PricingRule {
  path: string
  price_usd: string
  billing_scope: 'page' | 'path'
}

export interface SiteDetail {
  id: string
  domain: string | null
  default_price_usd: string
  default_billing_scope: 'page' | 'path'
  integration_type: string | null
  gating_enabled: boolean
  gated_categories: GatedCategories
  site_token_hash: string
  created_at: string
  use_publisher_defaults: boolean
  rules: PricingRule[]
  payout_wallet: string | null
  payout_network: string | null
}

export interface StatsBucket {
  earned_usd: string
  requests: number
  paid: number
}

export interface SiteStats {
  today: StatsBucket
  month: StatsBucket
  all_time: StatsBucket
}

export interface Transaction {
  id: string
  type: 'gate' | 'withdraw' | string
  path: string
  agent_name: string | null
  amount_usd: string
  status: string
  created_at: string
}

export interface TransactionsResponse {
  transactions: Transaction[]
  total: number
  page: number
  per_page: number
}

export interface CategoryBreakdownItem {
  category: string
  earned_usd: string
}

export interface CategoryBreakdownResponse {
  categories: CategoryBreakdownItem[]
}

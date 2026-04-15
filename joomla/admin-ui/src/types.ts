export interface XenarchAdmin {
  restUrl: string
  nonce: string
  csrfHeaderName: string
  settings: Settings
  pluginUrl: string
  version: string
  wcProjectId: string
}

export type BotCategoryKey = 'ai_search' | 'ai_assistants' | 'ai_agents' | 'ai_training' | 'scrapers' | 'general_ai'
export type BotAction = 'allow' | 'charge'
export type BotCategories = Record<BotCategoryKey, BotAction>
export type BotOverrides = Record<string, BotAction>

export interface BotSignature {
  name: string
  category: BotCategoryKey
  company: string
  last_seen: string | null
  first_seen: string | null
  hit_count: number
}

export interface PagePath {
  path: string
  title: string
}

export interface Settings {
  api_key: boolean
  site_id: string
  site_token: string
  default_price: string
  payout_wallet: string
  gate_unknown_traffic: string
  gate_enabled: string
  bot_categories: BotCategories
  bot_overrides: BotOverrides
  wallet_type: string
  wallet_network: string
  domain: string
  has_wallet: boolean
  has_site: boolean
  bot_signature_count: number
  pay_json_url: string
  xenarch_md_url: string
}

export interface PricingRule {
  path_contains: string
  price_usd: string
  billing_scope: 'page' | 'path'
}

export interface StatsBucket {
  earned_usd: string
  requests: number
  paid: number
}

export interface StatsResponse {
  today: StatsBucket
  month: StatsBucket
  all_time: StatsBucket
}

export interface Transaction {
  id: string
  type: string
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

export interface WalletBalanceResponse {
  balance_usd: string
}

export interface SellConfigCountry {
  id: string
  payment_methods: string[]
  subdivisions?: string[]
}

export interface SellConfig {
  countries: SellConfigCountry[]
  error?: string
}

export interface SellOptionsLimit {
  id: string
  min: string
  max: string
}

export interface SellOptionsCurrency {
  id: string
  limits: SellOptionsLimit[]
}

export interface SellOptions {
  cashout_currencies: SellOptionsCurrency[]
  sell_currencies?: Array<{ id: string; name: string; symbol: string }>
  error?: string
  supported?: boolean
}

export interface SellQuote {
  offramp_url: string
  sell_amount: string
  buy_amount: string
  fee_amount: string
  expires_at: string
}

declare global {
  interface Window {
    xenarchAdmin: XenarchAdmin
  }
}

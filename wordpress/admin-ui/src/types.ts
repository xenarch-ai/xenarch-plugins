export interface XenarchAdmin {
  restUrl: string
  nonce: string
  settings: Settings
  pluginUrl: string
  version: string
  wcProjectId: string
}

export interface Settings {
  api_key: boolean
  site_id: string
  site_token: string
  email: string
  default_price: string
  payout_wallet: string
  gate_unknown_traffic: string
  wallet_type: '' | 'external' | 'walletconnect'
  domain: string
  is_registered: boolean
  has_site: boolean
  bot_signature_count: number
  pay_json_url: string
  xenarch_md_url: string
}

export type SettingsChange = Settings | ((current: Settings) => Settings)

export type SettingsChangeHandler = (change: SettingsChange) => void

export interface PricingRule {
  path_contains: string
  price_usd: string
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

declare global {
  interface Window {
    xenarchAdmin: XenarchAdmin
  }
}

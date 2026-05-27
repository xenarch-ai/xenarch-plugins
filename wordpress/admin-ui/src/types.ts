// XEN-380: the plugin is a thin window into the platform. The only
// state it persists locally is the site_token bridge to the platform
// (and a per-server bot detection log). Everything else — payout,
// pricing, gating, transactions, earnings — lives on the backend and
// is rendered in dash.xenarch.dev.

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

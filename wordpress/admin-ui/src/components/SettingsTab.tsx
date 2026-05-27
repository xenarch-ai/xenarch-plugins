import type { Settings } from '../types'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
}

const DASHBOARD_SITES = 'https://dash.xenarch.dev/sites'

// XEN-380 — under the thin-window model, Pricing / Gating / Wallet all
// live on the platform and are edited in dash.xenarch.dev. This screen
// is a placeholder that deep-links the merchant straight there until a
// follow-up rewrite mirrors the dashboard's editing surface in-plugin.
export function SettingsTab({ settings }: Props) {
  const siteUrl = settings.site_id
    ? `${DASHBOARD_SITES}/${encodeURIComponent(settings.site_id)}`
    : DASHBOARD_SITES

  return (
    <div className="xenarch-section">
      <h2 className="xenarch-section-title">Settings — managed in dashboard</h2>
      <p className="xenarch-section-desc">
        Payout wallet, pricing rules, gating defaults and bot category
        overrides all live on the Xenarch backend. They're edited in
        dash.xenarch.dev so the same view of your site shows up here, on
        the dashboard, and anywhere else you connect.
      </p>
      <div style={{ marginTop: '1rem', display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
        <a
          href={siteUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="xenarch-btn xenarch-btn--primary"
        >
          Open this site in the dashboard →
        </a>
        <a
          href="https://dash.xenarch.dev/account/wallet"
          target="_blank"
          rel="noopener noreferrer"
          className="xenarch-btn"
        >
          Change payout wallet
        </a>
      </div>
    </div>
  )
}

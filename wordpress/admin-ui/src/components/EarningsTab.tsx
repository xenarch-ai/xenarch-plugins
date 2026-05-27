import type { Settings } from '../types'

interface Props {
  settings: Settings
}

const DASHBOARD_SITES = 'https://dash.xenarch.dev/sites'

// XEN-380 — earnings (stats, transactions, category breakdown, cash-out
// flow) all live on the platform. Until a follow-up wires the
// site-token-authed read endpoints into this tab, the plugin deep-links
// to the canonical view in dash.xenarch.dev.
export function EarningsTab({ settings }: Props) {
  const siteUrl = settings.site_id
    ? `${DASHBOARD_SITES}/${encodeURIComponent(settings.site_id)}`
    : DASHBOARD_SITES

  return (
    <div className="xenarch-section">
      <h2 className="xenarch-section-title">Earnings — view in dashboard</h2>
      <p className="xenarch-section-desc">
        Live earnings, paid requests, and the offramp flow are on
        dash.xenarch.dev. The plugin is connected and gating traffic —
        every paid request you receive shows up there in real time.
      </p>
      <div style={{ marginTop: '1rem' }}>
        <a
          href={siteUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="xenarch-btn xenarch-btn--primary"
        >
          Open earnings in the dashboard →
        </a>
      </div>
    </div>
  )
}

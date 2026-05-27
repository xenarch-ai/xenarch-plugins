import { useEffect, useMemo, useState } from 'react'
import type {
  Settings,
  SiteDetail,
  SiteStats,
  Transaction,
  CategoryBreakdownItem,
} from '../types'
import * as api from '../api'

interface Props {
  settings: Settings
}

const PERIODS = ['24h', '7d', '30d', 'all'] as const
type Period = (typeof PERIODS)[number]

function dollars(s: string | number | null | undefined): string {
  if (s == null) return '$0.00'
  const n = typeof s === 'number' ? s : parseFloat(s)
  if (!isFinite(n)) return '$0.00'
  return '$' + n.toFixed(n < 1 ? 4 : 2)
}

function truncate(addr: string | null | undefined): string {
  if (!addr) return '—'
  return addr.length > 12 ? `${addr.slice(0, 6)}…${addr.slice(-4)}` : addr
}

function relative(iso: string): string {
  const d = new Date(iso).getTime()
  const diff = (Date.now() - d) / 1000
  if (diff < 60) return `${Math.floor(diff)}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

export function EarningsTab({ settings }: Props) {
  const [site, setSite] = useState<SiteDetail | null>(null)
  const [stats, setStats] = useState<SiteStats | null>(null)
  const [categories, setCategories] = useState<CategoryBreakdownItem[]>([])
  const [period, setPeriod] = useState<Period>('all')
  const [txs, setTxs] = useState<Transaction[]>([])
  const [txTotal, setTxTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [txLoading, setTxLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Initial load: site detail + stats + category breakdown in parallel.
  useEffect(() => {
    setLoading(true)
    Promise.all([
      api.fetchSite(),
      api.fetchStats(),
      api.fetchCategoryBreakdown(),
    ])
      .then(([s, st, cb]) => {
        setSite(s)
        setStats(st)
        setCategories(cb.categories)
        setLoading(false)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Failed to load earnings.')
        setLoading(false)
      })
  }, [])

  // Transaction list refetches on period change.
  useEffect(() => {
    setTxLoading(true)
    api
      .fetchTransactions(period, 1, 25, 'all')
      .then((r) => {
        setTxs(r.transactions)
        setTxTotal(r.total)
        setTxLoading(false)
      })
      .catch(() => setTxLoading(false))
  }, [period])

  const dashboardSiteUrl = settings.site_id
    ? `https://dash.xenarch.dev/sites/${encodeURIComponent(settings.site_id)}`
    : 'https://dash.xenarch.dev/sites'

  const wallet = site?.payout_wallet ?? null

  const sortedCategories = useMemo(
    () => [...categories].sort((a, b) => parseFloat(b.earned_usd) - parseFloat(a.earned_usd)),
    [categories],
  )

  if (loading) {
    return (
      <div className="xenarch-section">
        <p className="xenarch-section-desc">Loading earnings…</p>
      </div>
    )
  }

  if (error) {
    return (
      <div className="xenarch-section">
        <h2 className="xenarch-section-title">Earnings</h2>
        <div className="xenarch-onboarding-error">{error}</div>
      </div>
    )
  }

  return (
    <>
      {/* Wallet bar */}
      <div className="xenarch-wallet-bar" style={{ marginBottom: '1rem' }}>
        <span className="xenarch-dot xenarch-dot--green" />
        <span className="xenarch-wallet-address" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
          {truncate(wallet)}
        </span>
        <span className="xenarch-wallet-label">
          {site?.payout_network || 'base'}
        </span>
        <a
          href="https://dash.xenarch.dev/account/wallet"
          target="_blank"
          rel="noopener noreferrer"
          style={{ marginLeft: 'auto', fontSize: '12px' }}
        >
          Change in dashboard →
        </a>
      </div>

      {/* Stats cards */}
      <div className="xenarch-stats-row" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '0.75rem', marginBottom: '1.25rem' }}>
        {stats &&
          [
            { label: 'Today', b: stats.today },
            { label: 'This month', b: stats.month },
            { label: 'All time', b: stats.all_time },
          ].map((c) => (
            <div key={c.label} className="xenarch-stats-card" style={{ padding: '1rem', borderRadius: '8px', background: 'var(--xn-surface, rgba(255,255,255,0.04))' }}>
              <div style={{ fontSize: '11px', opacity: 0.6, textTransform: 'uppercase' }}>{c.label}</div>
              <div style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: '24px', marginTop: '0.25rem' }}>
                {dollars(c.b.earned_usd)}
              </div>
              <div style={{ fontSize: '11px', opacity: 0.6, marginTop: '0.25rem' }}>
                {c.b.paid} paid · {c.b.requests} total
              </div>
            </div>
          ))}
      </div>

      {/* Category breakdown */}
      {sortedCategories.length > 0 && (
        <div className="xenarch-section">
          <h2 className="xenarch-section-title" style={{ fontSize: '14px' }}>Earnings by bot category</h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '0.5rem', marginTop: '0.5rem' }}>
            {sortedCategories.map((c) => (
              <div key={c.category} style={{ display: 'flex', justifyContent: 'space-between', padding: '0.5rem 0.75rem', borderRadius: '6px', background: 'var(--xn-surface, rgba(255,255,255,0.03))' }}>
                <span style={{ fontSize: '12px', opacity: 0.7 }}>{c.category.replace(/_/g, ' ')}</span>
                <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: '12px' }}>{dollars(c.earned_usd)}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Transactions table */}
      <div className="xenarch-section">
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.75rem' }}>
          <h2 className="xenarch-section-title" style={{ fontSize: '14px', margin: 0 }}>Recent activity</h2>
          <div className="xenarch-period-pills" style={{ display: 'flex', gap: '0.25rem' }}>
            {PERIODS.map((p) => (
              <button
                key={p}
                onClick={() => setPeriod(p)}
                className={period === p ? 'xenarch-btn xenarch-btn--primary' : 'xenarch-btn'}
                style={{ padding: '0.25rem 0.5rem', fontSize: '11px' }}
              >
                {p}
              </button>
            ))}
          </div>
        </div>
        {txLoading ? (
          <p className="xenarch-section-desc">Loading…</p>
        ) : txs.length === 0 ? (
          <p className="xenarch-section-desc">No activity in this period yet.</p>
        ) : (
          <table style={{ width: '100%', fontSize: '12px', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ opacity: 0.6, textAlign: 'left' }}>
                <th style={{ padding: '0.5rem 0.25rem' }}>Type</th>
                <th style={{ padding: '0.5rem 0.25rem' }}>Page</th>
                <th style={{ padding: '0.5rem 0.25rem' }}>Agent</th>
                <th style={{ padding: '0.5rem 0.25rem', textAlign: 'right' }}>Amount</th>
                <th style={{ padding: '0.5rem 0.25rem', textAlign: 'right' }}>Time</th>
              </tr>
            </thead>
            <tbody>
              {txs.map((t) => (
                <tr key={t.id} style={{ borderTop: '1px solid var(--xn-border, rgba(255,255,255,0.06))' }}>
                  <td style={{ padding: '0.5rem 0.25rem' }}>
                    <span style={{ fontSize: '10px', opacity: 0.7 }}>
                      {t.type === 'gate' ? (t.status === 'paid' ? 'earn' : 'block') : t.type}
                    </span>
                  </td>
                  <td style={{ padding: '0.5rem 0.25rem', fontFamily: "'JetBrains Mono', monospace" }}>{t.path}</td>
                  <td style={{ padding: '0.5rem 0.25rem' }}>{t.agent_name || '—'}</td>
                  <td style={{
                    padding: '0.5rem 0.25rem',
                    textAlign: 'right',
                    fontFamily: "'JetBrains Mono', monospace",
                    color: t.type === 'withdraw' ? 'var(--xn-error, #d33)' : (t.status === 'paid' ? 'var(--xn-success, #2a8)' : undefined),
                  }}>
                    {dollars(t.amount_usd)}
                  </td>
                  <td style={{ padding: '0.5rem 0.25rem', textAlign: 'right', opacity: 0.6 }}>{relative(t.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {txTotal > txs.length && (
          <div style={{ marginTop: '0.75rem', fontSize: '11px', opacity: 0.6 }}>
            Showing {txs.length} of {txTotal} · <a href={dashboardSiteUrl} target="_blank" rel="noopener noreferrer">see full history in dashboard →</a>
          </div>
        )}
      </div>
    </>
  )
}

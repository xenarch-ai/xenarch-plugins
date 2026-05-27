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
  return '$' + (n < 1 ? n.toFixed(4) : n.toFixed(2))
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

  useEffect(() => {
    setLoading(true)
    Promise.all([api.fetchSite(), api.fetchStats(), api.fetchCategoryBreakdown()])
      .then(([s, st, cb]) => {
        setSite(s); setStats(st); setCategories(cb.categories); setLoading(false)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Failed to load earnings.')
        setLoading(false)
      })
  }, [])

  useEffect(() => {
    setTxLoading(true)
    api
      .fetchTransactions(period, 1, 25, 'all')
      .then((r) => { setTxs(r.transactions); setTxTotal(r.total); setTxLoading(false) })
      .catch(() => setTxLoading(false))
  }, [period])

  const dashboardSiteUrl = settings.site_id
    ? `https://dash.xenarch.dev/sites/${encodeURIComponent(settings.site_id)}`
    : 'https://dash.xenarch.dev/sites'

  const sortedCategories = useMemo(
    () => [...categories].sort((a, b) => parseFloat(b.earned_usd) - parseFloat(a.earned_usd)),
    [categories],
  )

  if (loading) return <div className="empty">Loading earnings…</div>
  if (error) {
    return (
      <div className="section">
        <div className="section-head">
          <div className="section-title">Earnings</div>
          <div className="section-desc">Couldn't reach the platform.</div>
        </div>
        <div className="onboarding-error">{error}</div>
      </div>
    )
  }

  return (
    <>
      {/* Wallet bar — payout destination */}
      <div className="wallet-card">
        <span className="dot" />
        <span className="addr">{truncate(site?.payout_wallet)}</span>
        <span className="label">{site?.payout_network ?? 'base'}</span>
        <a
          className="change"
          href="https://dash.xenarch.dev/account/wallet"
          target="_blank"
          rel="noopener noreferrer"
        >
          Change in dashboard →
        </a>
      </div>

      {/* Stat cards — today / month / all-time */}
      {stats && (
        <div className="stats">
          {[
            { label: 'Today', b: stats.today },
            { label: 'This month', b: stats.month },
            { label: 'All time', b: stats.all_time },
          ].map((c) => (
            <div key={c.label} className="stat">
              <div className="lbl">{c.label}</div>
              <div className="val">{dollars(c.b.earned_usd)}</div>
              <div className="sub">{c.b.paid} paid · {c.b.requests} total</div>
            </div>
          ))}
        </div>
      )}

      {/* Category breakdown */}
      {sortedCategories.length > 0 && (
        <div className="section">
          <div className="section-head">
            <div className="section-title">Earnings by bot category</div>
            <div className="section-desc">Live, since this site started gating.</div>
          </div>
          <div className="agg">
            {sortedCategories.map((c) => (
              <div key={c.category} className="agg-cell">
                <div className="agg-label">{c.category.replace(/_/g, ' ')}</div>
                <div className="agg-value">{dollars(c.earned_usd)}</div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Activity table */}
      <div className="section">
        <div className="filter-strip">
          <div className="section-title" style={{ margin: 0 }}>Recent activity</div>
          <div className="fpills" style={{ marginLeft: 'auto' }}>
            {PERIODS.map((p) => (
              <span
                key={p}
                className={`fpill${period === p ? ' on' : ''}`}
                onClick={() => setPeriod(p)}
              >{p}</span>
            ))}
          </div>
        </div>

        {txLoading ? (
          <div className="empty">Loading…</div>
        ) : txs.length === 0 ? (
          <div className="empty">No activity in this period yet.</div>
        ) : (
          <table className="activity-table">
            <thead>
              <tr>
                <th>Type</th>
                <th>Page</th>
                <th>Agent</th>
                <th style={{ textAlign: 'right' }}>Amount</th>
                <th style={{ textAlign: 'right' }}>Time</th>
              </tr>
            </thead>
            <tbody>
              {txs.map((t) => {
                const kind = t.type === 'withdraw'
                  ? 'withdraw'
                  : t.status === 'paid' ? 'paid' : 'block'
                return (
                  <tr key={t.id}>
                    <td>
                      <span className={`type-pill${kind === 'block' ? ' block' : kind === 'withdraw' ? ' withdraw' : ''}`}>
                        {kind === 'withdraw' ? 'cash' : kind === 'block' ? 'block' : 'earn'}
                      </span>
                    </td>
                    <td className="mono">{t.path}</td>
                    <td>{t.agent_name ?? '—'}</td>
                    <td className={kind === 'block' ? 'amount-block' : 'amount-paid'}>
                      {dollars(t.amount_usd)}
                    </td>
                    <td className="time">{relative(t.created_at)}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        )}

        {txTotal > txs.length && (
          <div className="muted" style={{ marginTop: 12 }}>
            Showing {txs.length} of {txTotal} ·{' '}
            <a href={dashboardSiteUrl} target="_blank" rel="noopener noreferrer">
              see full history in dashboard →
            </a>
          </div>
        )}
      </div>
    </>
  )
}

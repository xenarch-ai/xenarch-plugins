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

const PERIODS = [
  { value: '24h', label: '24h' },
  { value: '7d',  label: '7d'  },
  { value: '30d', label: '30d' },
  { value: 'all', label: 'All' },
] as const
type Period = (typeof PERIODS)[number]['value']

const STATUS_FILTERS = [
  { value: 'all',     label: 'All'    },
  { value: 'paid',    label: 'Earned' },
  { value: 'blocked', label: 'Gated'  },
] as const
type StatusFilter = (typeof STATUS_FILTERS)[number]['value']

// Mirrors dashboard's STATUS_LABEL on /sites/[id]/activity — "pay" for
// paid rows, "gate" for blocked / pending. "cash" is for withdraws.
function pillKind(t: Transaction): 'pay' | 'gate' | 'cash' | 'free' {
  if (t.type === 'withdraw') return 'cash'
  if (t.status === 'paid') return 'pay'
  return 'gate'
}

function formatUsd(s: string | number | null | undefined): string {
  if (s == null) return '—'
  const n = typeof s === 'number' ? s : parseFloat(s)
  if (!isFinite(n) || n === 0) return '—'
  return '+$' + (n < 0.01 ? n.toFixed(4) : n.toFixed(2))
}

function dollarsHeader(s: string | number | null | undefined): string {
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
  if (!isFinite(d)) return iso
  const diff = Math.max(0, (Date.now() - d) / 1000)
  if (diff < 60) return `${Math.floor(diff)}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

export function EarningsTab({ settings }: Props) {
  const [site, setSite] = useState<SiteDetail | null>(null)
  const [stats, setStats] = useState<SiteStats | null>(null)
  const [categories, setCategories] = useState<CategoryBreakdownItem[]>([])

  // XEN-366: dashboard defaults — Earned/30d — so the merchant doesn't
  // open to a screen full of unpaid-bot noise. They can opt into Gated.
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('paid')
  const [period, setPeriod] = useState<Period>('30d')
  const [page, setPage] = useState(1)
  const perPage = 25

  const [txs, setTxs] = useState<Transaction[] | null>(null)
  const [txTotal, setTxTotal] = useState(0)
  const [loading, setLoading] = useState(true)
  const [txError, setTxError] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  // Initial load — site detail + stats + category breakdown.
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

  // Refetch transactions on any filter change.
  useEffect(() => {
    setTxError(null)
    api
      .fetchTransactions(period, page, perPage, statusFilter)
      .then((r) => { setTxs(r.transactions); setTxTotal(r.total) })
      .catch((err) => {
        setTxError(err instanceof Error ? err.message : 'Failed to load activity.')
        setTxs([])
      })
  }, [period, statusFilter, page])

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
      {/* Wallet bar */}
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

      {/* Stats — same 3 KPIs the dashboard's activity page uses */}
      {stats && (
        <div className="stats">
          <div className="stat">
            <div className="lbl">paid</div>
            <div className="val">{dollarsHeader(stats.today.earned_usd)}</div>
            <div className="sub">{stats.today.paid} paid requests · today</div>
          </div>
          <div className="stat">
            <div className="lbl">requests</div>
            <div className="val">{stats.today.requests}</div>
            <div className="sub">seen · today</div>
          </div>
          <div className="stat">
            <div className="lbl">gated</div>
            <div className="val">{Math.max(0, stats.today.requests - stats.today.paid)}</div>
            <div className="sub">non-paying · today</div>
          </div>
        </div>
      )}

      {/* Lifetime stats — duplicate of dashboard's "all time" trio */}
      {stats && (
        <div className="stats">
          <div className="stat">
            <div className="lbl">all time</div>
            <div className="val">{dollarsHeader(stats.all_time.earned_usd)}</div>
            <div className="sub">{stats.all_time.paid} paid · {stats.all_time.requests} total</div>
          </div>
          <div className="stat">
            <div className="lbl">this month</div>
            <div className="val">{dollarsHeader(stats.month.earned_usd)}</div>
            <div className="sub">{stats.month.paid} paid · {stats.month.requests} total</div>
          </div>
          <div className="stat">
            <div className="lbl">payout</div>
            <div className="val mono" style={{ fontSize: 14 }}>{truncate(site?.payout_wallet)}</div>
            <div className="sub">{site?.payout_network ?? 'base'}</div>
          </div>
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
                <div className="agg-value">{dollarsHeader(c.earned_usd)}</div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Activity table */}
      <div className="section">
        <div className="section-head">
          <div className="section-title">Recent activity</div>
          <div className="section-desc">Earned filters paid traffic; switch to Gated to see what would have paid.</div>
        </div>

        <div className="filters">
          <div className="fpills">
            {STATUS_FILTERS.map((p) => (
              <span
                key={p.value}
                className={`fpill${statusFilter === p.value ? ' on' : ''}`}
                onClick={() => { setStatusFilter(p.value); setPage(1) }}
              >{p.label}</span>
            ))}
          </div>
          <div className="fpills" style={{ marginLeft: 'auto' }}>
            {PERIODS.map((p) => (
              <span
                key={p.value}
                className={`fpill${period === p.value ? ' on' : ''}`}
                onClick={() => { setPeriod(p.value); setPage(1) }}
              >{p.label}</span>
            ))}
          </div>
        </div>

        {txError ? <div className="onboarding-error">{txError}</div> : null}

        <div className="tx-table activity">
          <div className="tx-row head">
            <div>Type</div>
            <div>Path</div>
            <div>Agent</div>
            <div style={{ textAlign: 'right' }}>Amount</div>
            <div style={{ textAlign: 'right' }}>Time</div>
          </div>
          {txs === null ? (
            <div className="tx-row"><span className="muted">Loading…</span></div>
          ) : txs.length === 0 ? (
            <div className="tx-row"><span className="muted">No activity yet for this filter.</span></div>
          ) : (
            txs.map((t) => {
              const kind = pillKind(t)
              const amt = formatUsd(t.amount_usd)
              const isZero = amt === '—'
              return (
                <div key={t.id} className="tx-row">
                  <span className={`tx-type ${kind}`}>{kind}</span>
                  <span className="tx-path">{t.path}</span>
                  <span className="tx-agent">{t.agent_name ?? '—'}</span>
                  <span className={`tx-amt${isZero ? ' zero' : ''}`}>{amt}</span>
                  <span className="tx-time">{relative(t.created_at)}</span>
                </div>
              )
            })
          )}
        </div>

        <div style={{ marginTop: 12, display: 'flex', gap: 8, alignItems: 'center' }}>
          <button className="btn secondary" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1}>‹ Prev</button>
          <span className="muted">Page {page} · {txTotal} total</span>
          <button className="btn secondary" onClick={() => setPage((p) => p + 1)} disabled={page * perPage >= txTotal}>Next ›</button>
          <a href={dashboardSiteUrl} target="_blank" rel="noopener noreferrer" className="btn ghost link" style={{ marginLeft: 'auto' }}>
            see full history in dashboard →
          </a>
        </div>
      </div>
    </>
  )
}

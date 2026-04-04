import { useState, useEffect, useCallback } from 'react'
import type {
  Settings,
  StatsResponse,
  TransactionsResponse,
  CategoryBreakdownItem,
  WalletBalanceResponse,
} from '../types'
import * as api from '../api'
import { CashOutModal } from './CashOutModal'

interface Props {
  settings: Settings
}

const PERIODS = [
  { value: '24h', label: '24h' },
  { value: '7d', label: '7d' },
  { value: '30d', label: '30d' },
  { value: 'all', label: 'All' },
]

const STATUS_FILTERS = [
  { value: 'all', label: 'All' },
  { value: 'paid', label: 'Paid' },
  { value: 'blocked', label: 'Gated' },
]

const CATEGORY_LABELS: Record<string, string> = {
  ai_search: 'AI Search',
  ai_assistants: 'AI Assistants',
  ai_agents: 'AI Agents',
  ai_training: 'AI Training',
  scrapers: 'Scrapers',
  general_ai: 'General AI',
}

function truncateWallet(addr: string): string {
  if (!addr || addr.length < 10) return addr
  return `${addr.slice(0, 6)}...${addr.slice(-4)}`
}

function formatNumber(n: number): string {
  return n.toLocaleString()
}

function formatTime(iso: string): string {
  const now = Date.now()
  const then = new Date(iso).getTime()
  const diffMs = now - then
  const diffMin = Math.floor(diffMs / 60000)

  if (diffMin < 1) return 'Just now'
  if (diffMin < 60) return `${diffMin} min ago`
  const diffHr = Math.floor(diffMin / 60)
  if (diffHr < 24) return `${diffHr}h ago`
  const diffDays = Math.floor(diffHr / 24)
  if (diffDays < 7) return `${diffDays}d ago`

  const d = new Date(iso)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

export function EarningsTab({ settings }: Props) {
  const [stats, setStats] = useState<StatsResponse | null>(null)
  const [transactions, setTransactions] = useState<TransactionsResponse | null>(null)
  const [breakdown, setBreakdown] = useState<CategoryBreakdownItem[] | null>(null)
  const [balance, setBalance] = useState<WalletBalanceResponse | null>(null)
  const [period, setPeriod] = useState('7d')
  const [statusFilter, setStatusFilter] = useState('all')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [txLoading, setTxLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [showCashOut, setShowCashOut] = useState(false)

  const isXenarch = settings.wallet_type === 'xenarch' || settings.wallet_type === 'coinbase'
  const wallet = settings.payout_wallet

  // ---- Data loading ----

  const loadEarnings = useCallback(() => {
    if (!settings.has_site) {
      setLoading(false)
      return
    }
    setError(null)
    setLoading(true)

    const promises: Promise<void>[] = [
      api.fetchStats().then(setStats),
      api.fetchCategoryBreakdown().then((r) => setBreakdown(r.categories)),
    ]
    if (isXenarch) {
      promises.push(api.fetchBalance().then(setBalance))
    }

    Promise.all(promises)
      .catch((err) => setError(err instanceof Error ? err.message : 'Failed to load'))
      .finally(() => setLoading(false))
  }, [settings.has_site, isXenarch])

  useEffect(() => {
    loadEarnings()
  }, [loadEarnings])

  // Fetch transactions when filters change
  useEffect(() => {
    if (!settings.has_site) return
    setTxLoading(true)
    api
      .fetchTransactions(period, page, 25, statusFilter)
      .then((data) => {
        if (page > 1 && transactions) {
          setTransactions({
            ...data,
            transactions: [...transactions.transactions, ...data.transactions],
          })
        } else {
          setTransactions(data)
        }
      })
      .catch(() => {})
      .finally(() => setTxLoading(false))
  }, [period, statusFilter, page, settings.has_site])

  const handlePeriodChange = (p: string) => {
    setPeriod(p)
    setPage(1)
  }

  const handleStatusChange = (t: string) => {
    setStatusFilter(t)
    setPage(1)
  }

  const handleRetry = () => {
    loadEarnings()
  }

  const handleLoadMore = () => {
    setPage((p) => p + 1)
  }

  const handleCashOutComplete = useCallback(() => {
    setShowCashOut(false)
    // Refresh balance + transactions
    if (isXenarch) {
      api.fetchBalance().then(setBalance).catch(() => {})
    }
    api.fetchTransactions(period, 1, 25, statusFilter).then(setTransactions).catch(() => {})
    setPage(1)
  }, [isXenarch, period, statusFilter])

  // ---- Helpers for zero-state detection ----

  const hasAnyEarnings =
    stats &&
    (parseFloat(stats.all_time.earned_usd) > 0 || stats.all_time.requests > 0)

  const hasBreakdownData =
    breakdown && breakdown.some((b) => parseFloat(b.earned_usd) > 0)

  const txRows = transactions?.transactions || []
  const txTotal = transactions?.total || 0
  const txPerPage = transactions?.per_page || 25
  const hasMorePages = txTotal > page * txPerPage

  // ---- Wallet row ----

  function renderWalletRow() {
    if (!wallet) return null

    const truncated = truncateWallet(wallet)
    const balanceUsd = balance ? `$${balance.balance_usd} USDC` : '$0.00 USDC'

    return (
      <div className="xenarch-earnings-wallet-row">
        <span className="xenarch-earnings-wallet-dot" />
        <span className="xenarch-earnings-wallet-addr">{truncated}</span>
        {isXenarch ? (
          <span className="xenarch-earnings-badge xenarch-earnings-badge--xenarch">
            xenarch wallet
          </span>
        ) : (
          <span className="xenarch-earnings-badge xenarch-earnings-badge--own">
            your wallet
          </span>
        )}
        {isXenarch && (
          <>
            <span className="xenarch-earnings-wallet-bal">{balanceUsd}</span>
            <button
              className="xenarch-earnings-btn-cashout"
              onClick={() => setShowCashOut(true)}
            >
              Cash out
            </button>
          </>
        )}
      </div>
    )
  }

  // ---- Stats cards ----

  function renderStats() {
    const zeroBucket = { earned_usd: '0.00', requests: 0, paid: 0 }
    const s = stats || { today: zeroBucket, month: zeroBucket, all_time: zeroBucket }

    const cards = [
      { label: 'Today', bucket: s.today },
      { label: 'This month', bucket: s.month },
      { label: 'All time', bucket: s.all_time },
    ]

    return (
      <div className="xenarch-earnings-stats">
        {cards.map((c) => (
          <div key={c.label} className="xenarch-earnings-stat">
            <div className="xenarch-earnings-stat-label">{c.label}</div>
            <div className="xenarch-earnings-stat-value">${c.bucket.earned_usd}</div>
            <div className="xenarch-earnings-stat-sub">
              {formatNumber(c.bucket.paid)} paid requests
            </div>
          </div>
        ))}
      </div>
    )
  }

  // ---- Category breakdown ----

  function renderBreakdown() {
    if (!hasBreakdownData || !breakdown) return null

    const sorted = [...breakdown]
      .filter((b) => parseFloat(b.earned_usd) > 0)
      .sort((a, b) => parseFloat(b.earned_usd) - parseFloat(a.earned_usd))

    return (
      <div className="xenarch-earnings-breakdown">
        {sorted.map((item) => (
          <div key={item.category} className="xenarch-earnings-bd-item">
            <span className="xenarch-earnings-bd-name">
              {CATEGORY_LABELS[item.category] || item.category}
            </span>
            <span className="xenarch-earnings-bd-amount">${item.earned_usd}</span>
          </div>
        ))}
      </div>
    )
  }

  // ---- Period row + type filter pills ----

  function renderPeriodRow() {
    return (
      <div className="xenarch-earnings-period-row">
        <span className="xenarch-earnings-period-title">Transactions</span>
        <div style={{ display: 'flex', gap: 8 }}>
          <div className="xenarch-earnings-pills">
            {STATUS_FILTERS.map((f) => (
              <button
                key={f.value}
                className={`xenarch-earnings-pill${statusFilter === f.value ? ' xenarch-earnings-pill--active' : ''}`}
                onClick={() => handleStatusChange(f.value)}
              >
                {f.label}
              </button>
            ))}
          </div>
          <div className="xenarch-earnings-pills">
            {PERIODS.map((p) => (
              <button
                key={p.value}
                className={`xenarch-earnings-pill${period === p.value ? ' xenarch-earnings-pill--active' : ''}`}
                onClick={() => handlePeriodChange(p.value)}
              >
                {p.label}
              </button>
            ))}
          </div>
        </div>
      </div>
    )
  }

  // ---- Transaction table ----

  function renderTransactions() {
    if (txLoading && txRows.length === 0) {
      return renderSkeletonRows()
    }

    if (txRows.length === 0) {
      // Empty period state vs zero earnings state
      if (hasAnyEarnings) {
        return (
          <div className="xenarch-earnings-empty">
            <div className="xenarch-earnings-empty-desc">
              No transactions for this period
            </div>
          </div>
        )
      }
      return (
        <div className="xenarch-earnings-empty">
          <div className="xenarch-earnings-empty-title">No paid requests yet</div>
          <div className="xenarch-earnings-empty-desc">
            When AI bots pay to access your content, transactions will appear here.
          </div>
        </div>
      )
    }

    return (
      <>
        <table className="xenarch-earnings-table">
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
            {txRows.map((tx) => {
              const isWithdraw = tx.type === 'withdraw'
              const isPaid = !isWithdraw && tx.status === 'paid'
              const isBlocked = !isWithdraw && tx.status !== 'paid'

              let pillClass = ' xenarch-earnings-tx-type--earn'
              let pillLabel = 'earn'
              if (isWithdraw) {
                pillClass = ' xenarch-earnings-tx-type--withdraw'
                pillLabel = 'withdraw'
              } else if (isBlocked) {
                pillClass = ' xenarch-earnings-tx-type--blocked'
                pillLabel = 'gated'
              }

              return (
                <tr key={tx.id}>
                  <td>
                    <span className={`xenarch-earnings-tx-type${pillClass}`}>
                      {pillLabel}
                    </span>
                  </td>
                  <td
                    className="xenarch-earnings-td-path"
                    style={isWithdraw ? { color: 'var(--xn-dot-red)' } : undefined}
                  >
                    {isWithdraw
                      ? `\u2192 ${truncateWallet(tx.path)}`
                      : tx.path}
                  </td>
                  <td className="xenarch-earnings-td-agent">
                    {tx.agent_name || '-'}
                  </td>
                  <td
                    className="xenarch-earnings-td-amount"
                    style={
                      isWithdraw
                        ? { color: 'var(--xn-dot-red)' }
                        : isBlocked
                          ? { color: 'var(--xn-text-muted)' }
                          : undefined
                    }
                  >
                    {isWithdraw ? `-$${tx.amount_usd}` : isPaid ? `+$${tx.amount_usd}` : `$${tx.amount_usd}`}
                  </td>
                  <td className="xenarch-earnings-td-time">
                    {formatTime(tx.created_at)}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
        {hasMorePages && (
          <div className="xenarch-earnings-more-row">
            <button className="xenarch-earnings-btn-more" onClick={handleLoadMore}>
              Load more
            </button>
          </div>
        )}
      </>
    )
  }

  // ---- Skeleton states ----

  function renderSkeletonStats() {
    return (
      <div className="xenarch-earnings-stats">
        {[1, 2, 3].map((i) => (
          <div key={i} className="xenarch-earnings-stat xenarch-earnings-stat--skeleton">
            <div className="xenarch-skeleton" style={{ width: '50%', height: 12, marginBottom: 8 }} />
            <div className="xenarch-skeleton" style={{ width: '70%', height: 28, marginBottom: 8 }} />
            <div className="xenarch-skeleton" style={{ width: '60%', height: 10 }} />
          </div>
        ))}
      </div>
    )
  }

  function renderSkeletonRows() {
    const widths = [
      { pill: 48, path: 160, agent: 80, amount: 60, time: 50 },
      { pill: 48, path: 140, agent: 90, amount: 55, time: 50 },
      { pill: 48, path: 180, agent: 70, amount: 50, time: 50 },
      { pill: 48, path: 120, agent: 100, amount: 65, time: 50 },
      { pill: 48, path: 150, agent: 85, amount: 45, time: 50 },
    ]
    return (
      <>
        {widths.map((w, i) => (
          <div key={i} className="xenarch-earnings-skeleton-row">
            <div className="xenarch-skeleton" style={{ width: w.pill, height: 22, borderRadius: 4 }} />
            <div className="xenarch-skeleton" style={{ width: w.path, height: 12, borderRadius: 4 }} />
            <div className="xenarch-skeleton" style={{ width: w.agent, height: 12, borderRadius: 4 }} />
            <div className="xenarch-skeleton" style={{ width: w.amount, height: 12, borderRadius: 4, marginLeft: 'auto' }} />
            <div className="xenarch-skeleton" style={{ width: w.time, height: 12, borderRadius: 4 }} />
          </div>
        ))}
      </>
    )
  }

  // ---- Error state ----

  if (error) {
    return (
      <>
        {renderWalletRow()}
        <div className="xenarch-earnings-empty" style={{ padding: '64px 24px' }}>
          <div className="xenarch-earnings-empty-title">
            Couldn&apos;t load earnings data
          </div>
          <div className="xenarch-earnings-empty-desc">
            Check your connection and{' '}
            <span className="xenarch-earnings-error-link" onClick={handleRetry}>
              try again
            </span>
            .
          </div>
        </div>
      </>
    )
  }

  // ---- Loading state ----

  if (loading) {
    return (
      <>
        {renderWalletRow()}
        {renderSkeletonStats()}
        {renderPeriodRow()}
        {renderSkeletonRows()}
      </>
    )
  }

  // ---- Normal / zero earnings / empty period states ----

  return (
    <>
      {renderWalletRow()}
      {renderStats()}
      {renderBreakdown()}
      {renderPeriodRow()}
      {renderTransactions()}
      {showCashOut && (
        <CashOutModal
          balance={balance?.balance_usd || '0.00'}
          onComplete={handleCashOutComplete}
          onClose={() => setShowCashOut(false)}
        />
      )}
    </>
  )
}

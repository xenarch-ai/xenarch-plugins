import { useState, useEffect } from 'react'
import type { Settings, StatsResponse, TransactionsResponse } from '../types'
import * as api from '../api'
import { StatsCards } from './StatsCards'
import { WalletBar } from './WalletBar'
import { TransactionTable } from './TransactionTable'
import { PeriodFilter } from './PeriodFilter'

interface Props {
  settings: Settings
}

export function EarningsTab({ settings }: Props) {
  const [stats, setStats] = useState<StatsResponse | null>(null)
  const [transactions, setTransactions] = useState<TransactionsResponse | null>(null)
  const [period, setPeriod] = useState('all')
  const [statusFilter, setStatusFilter] = useState('paid')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Fetch stats on mount.
  useEffect(() => {
    api.fetchStats()
      .then(setStats)
      .catch((err) => setError(err instanceof Error ? err.message : 'Failed to load stats'))
      .finally(() => setLoading(false))
  }, [])

  // Fetch transactions when period, status, or page changes.
  useEffect(() => {
    api.fetchTransactions(period, page, 25, statusFilter)
      .then(setTransactions)
      .catch(() => {})
  }, [period, statusFilter, page])

  const handlePeriodChange = (p: string) => {
    setPeriod(p)
    setPage(1)
  }

  const handleStatusChange = (s: string) => {
    setStatusFilter(s)
    setPage(1)
  }

  const handleRetry = () => {
    setError(null)
    setLoading(true)
    api.fetchStats()
      .then(setStats)
      .catch((err) => setError(err instanceof Error ? err.message : 'Failed to load stats'))
      .finally(() => setLoading(false))
  }

  if (error) {
    return (
      <div className="xenarch-card">
        <div className="xenarch-notice xenarch-notice--error">{error}</div>
        <button className="xenarch-btn xenarch-btn--secondary" onClick={handleRetry}>
          Retry
        </button>
      </div>
    )
  }

  if (loading) {
    return (
      <div className="xenarch-stats-grid">
        {[1, 2, 3].map((i) => (
          <div key={i} className="xenarch-stat-card">
            <div className="xenarch-skeleton" style={{ height: 32, marginBottom: 8 }} />
            <div className="xenarch-skeleton" style={{ height: 14, width: '60%', margin: '0 auto' }} />
          </div>
        ))}
      </div>
    )
  }

  return (
    <>
      <WalletBar settings={settings} />
      {stats && <StatsCards stats={stats} />}

      <div className="xenarch-card">
        <div className="xenarch-flex-between" style={{ marginBottom: 12 }}>
          <h3 style={{ margin: 0 }}>Transactions</h3>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <div className="xenarch-period-filter">
              {[
                { value: 'paid', label: 'Paid' },
                { value: 'blocked', label: 'Blocked' },
                { value: 'all', label: 'All' },
              ].map((s) => (
                <button
                  key={s.value}
                  className={`xenarch-period-btn ${statusFilter === s.value ? 'xenarch-period-btn--active' : ''}`}
                  onClick={() => handleStatusChange(s.value)}
                >
                  {s.label}
                </button>
              ))}
            </div>
            <PeriodFilter period={period} onChange={handlePeriodChange} />
          </div>
        </div>

        {transactions && (
          <TransactionTable
            transactions={transactions}
            page={page}
            onPageChange={setPage}
          />
        )}
      </div>
    </>
  )
}

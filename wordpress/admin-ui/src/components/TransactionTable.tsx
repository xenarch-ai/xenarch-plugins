import type { TransactionsResponse } from '../types'

interface Props {
  transactions: TransactionsResponse
  page: number
  onPageChange: (page: number) => void
}

export function TransactionTable({ transactions, page, onPageChange }: Props) {
  const { transactions: rows, total, per_page } = transactions
  const totalPages = Math.ceil(total / per_page)

  if (rows.length === 0) {
    return (
      <div className="xenarch-empty">
        No transactions found for this period.
      </div>
    )
  }

  const formatTime = (iso: string) => {
    const d = new Date(iso)
    return d.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  const statusLabel = (s: string) => {
    if (s === 'paid') return 'paid'
    return 'blocked'
  }

  const pillClass = (s: string) => {
    if (s === 'paid') return 'xenarch-pill--paid'
    return 'xenarch-pill--blocked'
  }

  return (
    <>
      <table className="xenarch-table">
        <thead>
          <tr>
            <th>Page Path</th>
            <th>Agent</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((tx) => (
            <tr key={tx.id}>
              <td title={tx.path}>{tx.path.length > 40 ? tx.path.slice(0, 40) + '...' : tx.path}</td>
              <td>{tx.agent_name || '-'}</td>
              <td>${tx.amount_usd}</td>
              <td>
                <span className={`xenarch-pill ${pillClass(tx.status)}`}>
                  <span className="xenarch-pill-dot" />
                  {statusLabel(tx.status)}
                </span>
              </td>
              <td>{formatTime(tx.created_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>

      {totalPages > 1 && (
        <div className="xenarch-pagination">
          <span>
            Showing {(page - 1) * per_page + 1}–{Math.min(page * per_page, total)} of {total}
          </span>
          <div style={{ display: 'flex', gap: 4 }}>
            <button
              className="xenarch-btn xenarch-btn--secondary xenarch-btn--small"
              disabled={page <= 1}
              onClick={() => onPageChange(page - 1)}
            >
              Prev
            </button>
            <button
              className="xenarch-btn xenarch-btn--secondary xenarch-btn--small"
              disabled={page >= totalPages}
              onClick={() => onPageChange(page + 1)}
            >
              Next
            </button>
          </div>
        </div>
      )}
    </>
  )
}

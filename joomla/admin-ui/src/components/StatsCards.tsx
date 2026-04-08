import type { StatsResponse } from '../types'

interface Props {
  stats: StatsResponse
}

export function StatsCards({ stats }: Props) {
  const cards = [
    { label: 'Today', bucket: stats.today },
    { label: 'This Month', bucket: stats.month },
    { label: 'All Time', bucket: stats.all_time },
  ]

  return (
    <div className="xenarch-stats-grid">
      {cards.map((card) => (
        <div key={card.label} className="xenarch-stat-card">
          <div className="xenarch-stat-value">${card.bucket.earned_usd}</div>
          <div className="xenarch-stat-sub">
            {card.bucket.paid} paid / {card.bucket.requests} requests
          </div>
          <div className="xenarch-stat-label">{card.label}</div>
        </div>
      ))}
    </div>
  )
}

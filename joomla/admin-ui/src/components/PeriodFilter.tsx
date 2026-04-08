interface Props {
  period: string
  onChange: (period: string) => void
}

const PERIODS = [
  { value: '24h', label: '24h' },
  { value: '7d', label: '7d' },
  { value: '30d', label: '30d' },
  { value: 'all', label: 'All' },
]

export function PeriodFilter({ period, onChange }: Props) {
  return (
    <div className="xenarch-period-filter">
      {PERIODS.map((p) => (
        <button
          key={p.value}
          className={`xenarch-period-btn ${period === p.value ? 'xenarch-period-btn--active' : ''}`}
          onClick={() => onChange(p.value)}
        >
          {p.label}
        </button>
      ))}
    </div>
  )
}

import { useState, useRef, useEffect } from 'react'

interface Props {
  value: string
  onChange: (value: string) => void
}

const OPTIONS = [
  { value: 'base', label: 'Base' },
  { value: 'solana', label: 'Solana' },
]

export function NetworkSelect({ value, onChange }: Props) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const handleClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handleClick)
    return () => document.removeEventListener('mousedown', handleClick)
  }, [open])

  const selected = OPTIONS.find((o) => o.value === value) || OPTIONS[0]!

  return (
    <div className="xenarch-network-select" ref={ref}>
      <button
        type="button"
        className="xenarch-network-select-trigger"
        onClick={() => setOpen((p) => !p)}
      >
        {selected.label}
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"><path d="M3 5l3 3 3-3"/></svg>
      </button>
      {open && (
        <div className="xenarch-network-select-menu">
          {OPTIONS.map((o) => (
            <button
              key={o.value}
              type="button"
              className={`xenarch-network-select-option${o.value === value ? ' xenarch-network-select-option--active' : ''}`}
              onClick={() => { onChange(o.value); setOpen(false) }}
            >
              {o.label}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

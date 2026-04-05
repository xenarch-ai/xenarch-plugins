import { useState, useRef, useEffect } from 'react'

interface Option {
  value: string
  label: string
}

interface Props {
  value: string
  options: Option[]
  placeholder?: string
  onChange: (value: string) => void
  disabled?: boolean
}

export function CustomSelect({ value, options, placeholder, onChange, disabled }: Props) {
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

  const selected = options.find((o) => o.value === value)
  const label = selected ? selected.label : placeholder || 'Select...'

  return (
    <div className="xenarch-network-select" ref={ref}>
      <button
        type="button"
        className="xenarch-network-select-trigger"
        onClick={() => { if (!disabled) setOpen((p) => !p) }}
        disabled={disabled}
      >
        {label}
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"><path d="M3 5l3 3 3-3"/></svg>
      </button>
      {open && (
        <div className="xenarch-network-select-menu">
          {options.map((o) => (
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

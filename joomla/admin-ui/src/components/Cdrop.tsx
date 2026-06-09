import { useEffect, useRef, useState } from 'react'

// XEN-435 P4: custom dropdown ported from the dashboard's Cdrop primitive —
// the design system never uses OS-rendered <select> menus (they ignore the
// dark theme). Div-based trigger + menu, styled via .cdrop* in admin.css.
export interface CdropOption {
  value: string
  name: string
  hint?: string
  triggerLabel?: string
  disabled?: boolean
}

export function Cdrop({
  options,
  value,
  onChange,
  disabled,
}: {
  options: CdropOption[]
  value: string
  onChange: (v: string) => void
  disabled?: boolean
}) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    if (!open) return
    function onDocClick(e: MouseEvent) {
      if (!ref.current?.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('click', onDocClick)
    return () => document.removeEventListener('click', onDocClick)
  }, [open])

  const current = options.find((o) => o.value === value) ?? options[0]

  return (
    <div ref={ref} className={`cdrop${open ? ' open' : ''}`} data-current={value}>
      <button
        type="button"
        className="cdrop-trigger"
        disabled={disabled}
        onClick={(e) => {
          e.stopPropagation()
          if (!disabled) setOpen((o) => !o)
        }}
      >
        {current?.triggerLabel ?? current?.name ?? ''}
      </button>
      <div className="cdrop-menu">
        {options.map((o) => (
          <div
            key={o.value}
            className={`cdrop-item${o.value === value ? ' on' : ''}${o.disabled ? ' disabled' : ''}`}
            data-value={o.value}
            aria-disabled={o.disabled || undefined}
            onClick={() => {
              if (o.disabled) return
              onChange(o.value)
              setOpen(false)
            }}
          >
            <span className="check">✓</span>
            <span className="text">
              <span className="name">{o.name}</span>
              {o.hint && <span className="hint">{o.hint}</span>}
            </span>
          </div>
        ))}
      </div>
    </div>
  )
}

import { useState, useRef, useEffect } from 'react'
import type { PricingRule, PagePath } from '../types'
import * as api from '../api'

interface Props {
  index: number
  rule: PricingRule
  onChange: (index: number, rule: PricingRule) => void
  onDelete: (index: number) => void
  onMove: (from: number, to: number) => void
  onPathBlur?: (index: number) => void
  autoFocus?: boolean
}

export function PricingRuleRow({ index, rule, onChange, onDelete, onMove, onPathBlur, autoFocus }: Props) {
  const [suggestions, setSuggestions] = useState<PagePath[]>([])
  const [showSuggestions, setShowSuggestions] = useState(false)
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null)
  const rowRef = useRef<HTMLDivElement>(null)

  const isFree = rule.price_usd === '0' || rule.price_usd === '0.00' || rule.price_usd === '0.000'
  const scope = rule.billing_scope || 'page'

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (rowRef.current && !rowRef.current.contains(e.target as Node)) {
        setShowSuggestions(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const handlePathChange = (value: string) => {
    onChange(index, { ...rule, path_contains: value })
    if (searchTimer.current) clearTimeout(searchTimer.current)
    if (value.length >= 2) {
      searchTimer.current = setTimeout(() => {
        api.searchPagePaths(value).then(data => {
          setSuggestions(data.paths)
          setShowSuggestions(data.paths.length > 0)
        }).catch(() => {})
      }, 300)
    } else {
      setShowSuggestions(false)
    }
  }

  const pickSuggestion = (path: string) => {
    onChange(index, { ...rule, path_contains: path })
    setShowSuggestions(false)
  }

  const handleDragStart = (e: React.DragEvent) => {
    e.dataTransfer.setData('text/plain', String(index))
    e.dataTransfer.effectAllowed = 'move'
  }

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
    e.dataTransfer.dropEffect = 'move'
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    const from = parseInt(e.dataTransfer.getData('text/plain'), 10)
    if (!isNaN(from) && from !== index) {
      onMove(from, index)
    }
  }

  return (
    <div
      ref={rowRef}
      className="xenarch-rule-row"
      style={{ position: 'relative' }}
      draggable
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDrop={handleDrop}
    >
      <span className="xenarch-rule-grip" title="Drag to reorder">&#x2807;</span>
      <span className="xenarch-rule-match">URL contains</span>
      <input
        className="xenarch-input xenarch-rule-path"
        type="text"
        value={rule.path_contains}
        onChange={(e) => handlePathChange(e.target.value)}
        onFocus={() => { if (suggestions.length > 0) setShowSuggestions(true) }}
        onBlur={() => { if (onPathBlur) onPathBlur(index) }}
        placeholder="/path/"
        autoFocus={autoFocus}
      />
      <span className="xenarch-rule-arrow">&rarr;</span>
      <span className="xenarch-rule-dollar">$</span>
      {isFree ? (
        <input
          className="xenarch-input xenarch-rule-price xenarch-rule-price--free"
          type="text"
          value="FREE"
          readOnly
          onClick={() => onChange(index, { ...rule, price_usd: '' })}
        />
      ) : (
        <input
          className="xenarch-input xenarch-rule-price"
          type="text"
          value={rule.price_usd}
          onChange={(e) => onChange(index, { ...rule, price_usd: e.target.value })}
          placeholder="0.00"
        />
      )}
      <div className="xenarch-action-seg xenarch-rule-scope">
        <button
          className={`xenarch-action-btn${scope === 'page' ? ' xenarch-action-btn--active' : ''}`}
          onClick={() => onChange(index, { ...rule, billing_scope: 'page' })}
        >
          per page
        </button>
        <button
          className={`xenarch-action-btn${scope === 'path' ? ' xenarch-action-btn--active' : ''}`}
          onClick={() => onChange(index, { ...rule, billing_scope: 'path' })}
        >
          per path
        </button>
      </div>
      <button
        className="xenarch-rule-remove"
        onClick={() => onDelete(index)}
        title="Delete rule"
      >
        &times;
      </button>

      {showSuggestions && (
        <div className="xenarch-path-suggest">
          {suggestions.map((s, i) => (
            <div key={i} className="xenarch-suggest-item" onMouseDown={() => pickSuggestion(s.path)}>
              <span className="xenarch-suggest-path">{s.path}</span>
              <span className="xenarch-suggest-title">{s.title}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

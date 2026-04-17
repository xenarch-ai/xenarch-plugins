import { useState, useEffect, useCallback, useRef } from 'react'
import type { Settings, PricingRule } from '../types'
import * as api from '../api'
import { PricingRuleRow } from './PricingRuleRow'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
  loading?: boolean
}

export function PricingSection({ settings, onSettingsChange, loading }: Props) {
  const [defaultPrice, setDefaultPrice] = useState(settings.default_price)
  const [rules, setRules] = useState<PricingRule[]>([])
  const [loaded, setLoaded] = useState(false)
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    api.fetchPricingRules().then((data) => {
      setRules(data.rules)
      setLoaded(true)
    }).catch(() => setLoaded(true))
  }, [])

  // Keep defaultPrice in sync if settings change externally.
  useEffect(() => {
    setDefaultPrice(settings.default_price)
  }, [settings.default_price])

  // Auto-save rules (debounced).
  const saveRules = useCallback((newRules: PricingRule[]) => {
    const validRules = newRules.filter((r) => r.path_contains.trim() !== '')
    api.savePricingRules(validRules).catch(() => {})
  }, [])

  const debouncedSaveRules = useCallback((newRules: PricingRule[]) => {
    if (saveTimer.current) clearTimeout(saveTimer.current)
    saveTimer.current = setTimeout(() => saveRules(newRules), 500)
  }, [saveRules])

  // Auto-save default price on blur.
  const handlePriceBlur = useCallback(() => {
    api.updateSettings({ xenarch_default_price: defaultPrice })
      .then(onSettingsChange)
      .catch(() => {})
  }, [defaultPrice, onSettingsChange])

  const addRule = useCallback(() => {
    setRules((prev) => [...prev, { path_contains: '', price_usd: '0.003', billing_scope: 'page' }])
  }, [])

  const updateRule = useCallback((index: number, updated: PricingRule) => {
    setRules((prev) => {
      const next = prev.map((r, i) => (i === index ? updated : r))
      debouncedSaveRules(next)
      return next
    })
  }, [debouncedSaveRules])

  const deleteRule = useCallback((index: number) => {
    setRules((prev) => {
      const next = prev.filter((_, i) => i !== index)
      saveRules(next)
      return next
    })
  }, [saveRules])

  // Remove empty rules when path input loses focus.
  const handleRuleBlur = useCallback((index: number) => {
    setRules((prev) => {
      if (prev[index] && prev[index].path_contains.trim() === '') {
        const next = prev.filter((_, i) => i !== index)
        saveRules(next)
        return next
      }
      return prev
    })
  }, [saveRules])

  const moveRule = useCallback((from: number, to: number) => {
    setRules((prev) => {
      const next = [...prev]
      const [item] = next.splice(from, 1)
      if (item) next.splice(to, 0, item)
      saveRules(next)
      return next
    })
  }, [saveRules])

  // Loading skeleton
  if (loading) {
    return (
      <div className="xenarch-section">
        <div className="xenarch-skeleton" style={{ width: 80, height: 16, marginBottom: 6 }} />
        <div className="xenarch-skeleton" style={{ width: 300, height: 12, marginBottom: 16 }} />
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 16 }}>
          <div className="xenarch-skeleton" style={{ width: 90, height: 12 }} />
          <div className="xenarch-skeleton" style={{ width: 80, height: 38, borderRadius: 6 }} />
          <div className="xenarch-skeleton" style={{ width: 50, height: 12 }} />
        </div>
        <div className="xenarch-skeleton" style={{ width: '100%', height: 42, borderRadius: 8, marginBottom: 8 }} />
        <div className="xenarch-skeleton" style={{ width: '100%', height: 42, borderRadius: 8 }} />
      </div>
    )
  }

  return (
    <div className="xenarch-section">
      <div className="xenarch-section-title">Pricing</div>
      <div className="xenarch-section-desc">Set pricing for bot traffic. Choose per-page or per-path billing for each rule.</div>

      {/* Default price row */}
      <div className="xenarch-pricing-row">
        <label className="xenarch-pricing-label">Default price</label>
        <span className="xenarch-pricing-symbol">$</span>
        <input
          type="text"
          className="xenarch-input xenarch-pricing-price-input"
          value={defaultPrice}
          onChange={(e) => setDefaultPrice(e.target.value)}
          onBlur={handlePriceBlur}
          onKeyDown={(e) => { if (e.key === 'Enter') handlePriceBlur() }}
        />
        <span className="xenarch-pricing-unit">per page</span>
      </div>

      {loaded && (
        <div style={{ marginTop: 12 }}>
          {rules.map((rule, i) => (
            <PricingRuleRow
              key={i}
              index={i}
              rule={rule}
              onChange={updateRule}
              onDelete={deleteRule}
              onMove={moveRule}
              onPathBlur={handleRuleBlur}
              autoFocus={rule.path_contains === ''}
            />
          ))}

          <button
            className="xenarch-btn-add"
            onClick={addRule}
          >
            + add rule
          </button>
        </div>
      )}
    </div>
  )
}

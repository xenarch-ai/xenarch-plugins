import { useState, useEffect, useCallback } from 'react'
import type { Settings, PricingRule } from '../types'
import * as api from '../api'
import { PricingRuleRow } from './PricingRuleRow'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
}

export function PricingSection({ settings, onSettingsChange }: Props) {
  const [defaultPrice, setDefaultPrice] = useState(settings.default_price)
  const [rules, setRules] = useState<PricingRule[]>([])
  const [saving, setSaving] = useState(false)
  const [notice, setNotice] = useState<{ type: 'success' | 'error'; msg: string } | null>(null)
  const [loaded, setLoaded] = useState(false)

  useEffect(() => {
    api.fetchPricingRules().then((data) => {
      setRules(data.rules)
      setLoaded(true)
    }).catch(() => {
      setLoaded(true)
    })
  }, [])

  const addRule = useCallback(() => {
    setRules((prev) => [...prev, { path_contains: '', price_usd: '0.003' }])
  }, [])

  const updateRule = useCallback((index: number, updated: PricingRule) => {
    setRules((prev) => prev.map((r, i) => (i === index ? updated : r)))
  }, [])

  const deleteRule = useCallback((index: number) => {
    setRules((prev) => prev.filter((_, i) => i !== index))
  }, [])

  const moveRule = useCallback((from: number, to: number) => {
    setRules((prev) => {
      const next = [...prev]
      const [item] = next.splice(from, 1)
      if (item) next.splice(to, 0, item)
      return next
    })
  }, [])

  const handleSave = async () => {
    setSaving(true)
    setNotice(null)
    try {
      // Save default price.
      const updated = await api.updateSettings({
        xenarch_default_price: defaultPrice,
      })
      onSettingsChange(updated)

      // Save pricing rules.
      const validRules = rules.filter((r) => r.path_contains.trim() !== '')
      await api.savePricingRules(validRules)
      setRules(validRules)

      setNotice({ type: 'success', msg: 'Pricing settings saved.' })
    } catch (err) {
      setNotice({
        type: 'error',
        msg: err instanceof Error ? err.message : 'Failed to save pricing',
      })
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="xenarch-card">
      <h3>Pricing</h3>

      {notice && (
        <div className={`xenarch-notice xenarch-notice--${notice.type}`}>{notice.msg}</div>
      )}

      <div className="xenarch-field">
        <label htmlFor="xen-default-price">Default Price (USD)</label>
        <input
          id="xen-default-price"
          type="number"
          className="xenarch-input"
          value={defaultPrice}
          onChange={(e) => setDefaultPrice(e.target.value)}
          step="0.001"
          min="0"
          max="1"
        />
        <div className="xenarch-description">
          Amount in USD charged per AI agent page view (max $1.00). Set to 0 for free access.
        </div>
      </div>

      {loaded && (
        <>
          <h3 style={{ marginTop: 16 }}>Path Pricing Rules</h3>
          <p>First matching rule wins. Drag to reorder. Set price to $0 for free paths.</p>

          {rules.map((rule, i) => (
            <PricingRuleRow
              key={i}
              index={i}
              rule={rule}
              onChange={updateRule}
              onDelete={deleteRule}
              onMove={moveRule}
            />
          ))}

          <div style={{ marginBottom: 16 }}>
            <button
              className="xenarch-btn xenarch-btn--secondary xenarch-btn--small"
              onClick={addRule}
            >
              + Add Rule
            </button>
          </div>
        </>
      )}

      <button
        className="xenarch-btn xenarch-btn--primary"
        onClick={handleSave}
        disabled={saving}
      >
        {saving && <span className="xenarch-spinner" />}
        Save Pricing
      </button>
    </div>
  )
}

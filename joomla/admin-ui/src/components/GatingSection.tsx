import { useState, useEffect, useCallback, useRef } from 'react'
import type { Settings, BotCategoryKey, BotAction, BotSignature } from '../types'
import * as api from '../api'
import { BotDetailModal } from './BotDetailModal'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
  loading?: boolean
}

interface CategoryMeta {
  key: BotCategoryKey
  name: string
  description: string
  defaultAction: BotAction
}

const CATEGORIES: CategoryMeta[] = [
  { key: 'ai_search', name: 'AI Search', description: 'Index your site so AI can recommend it', defaultAction: 'allow' },
  { key: 'ai_assistants', name: 'AI Assistants', description: 'Users asking AI about your content', defaultAction: 'charge' },
  { key: 'ai_agents', name: 'AI Agents', description: 'Autonomous AI performing tasks on your site', defaultAction: 'charge' },
  { key: 'ai_training', name: 'AI Training', description: 'Crawlers collecting data for model training', defaultAction: 'charge' },
  { key: 'scrapers', name: 'Scrapers', description: 'Automated data extraction tools', defaultAction: 'charge' },
  { key: 'general_ai', name: 'General AI', description: 'Other AI crawlers with mixed or unclear intent', defaultAction: 'charge' },
]

export function GatingSection({ settings, onSettingsChange, loading }: Props) {
  const [signatures, setSignatures] = useState<BotSignature[]>([])
  const [modalOpen, setModalOpen] = useState(false)
  const [modalCategory, setModalCategory] = useState<BotCategoryKey | 'all'>('all')
  const [savingMaster, setSavingMaster] = useState(false)
  const [savingCat, setSavingCat] = useState<string | null>(null)
  const masterRef = useRef(settings.gate_enabled)

  const masterEnabled = settings.gate_enabled === '1'
  const categories = settings.bot_categories
  const overrides = settings.bot_overrides

  useEffect(() => {
    api.fetchBotSignatures().then(data => setSignatures(data.signatures)).catch(() => {})
  }, [])

  // Keep ref in sync for snap-back.
  useEffect(() => {
    masterRef.current = settings.gate_enabled
  }, [settings.gate_enabled])

  const handleMasterToggle = useCallback(async () => {
    const prev = masterRef.current
    const newValue = masterEnabled ? '0' : '1'
    // Optimistic: update UI immediately.
    onSettingsChange({ ...settings, gate_enabled: newValue })
    setSavingMaster(true)
    try {
      const updated = await api.updateSettings({ xenarch_gate_enabled: newValue })
      onSettingsChange(updated)
    } catch {
      // Snap back to previous state on failure.
      onSettingsChange({ ...settings, gate_enabled: prev })
    }
    setSavingMaster(false)
  }, [masterEnabled, settings, onSettingsChange])

  const handleCategoryToggle = useCallback(async (key: BotCategoryKey) => {
    if (!masterEnabled) return
    const current = categories[key] || 'charge'
    const newAction: BotAction = current === 'charge' ? 'allow' : 'charge'
    const prevCategories = { ...categories }
    // Optimistic update.
    onSettingsChange({ ...settings, bot_categories: { ...categories, [key]: newAction } })
    setSavingCat(key)
    try {
      const updated = await api.updateBotCategories({ [key]: newAction })
      onSettingsChange({ ...settings, bot_categories: updated })
    } catch {
      // Snap back.
      onSettingsChange({ ...settings, bot_categories: prevCategories })
    }
    setSavingCat(null)
  }, [masterEnabled, categories, settings, onSettingsChange])

  const handleOverrideChange = useCallback(async (updates: Record<string, BotAction | null>) => {
    try {
      const updated = await api.updateBotOverrides(updates)
      onSettingsChange({ ...settings, bot_overrides: updated })
    } catch {}
  }, [settings, onSettingsChange])

  const openModal = (catKey: BotCategoryKey | 'all') => {
    setModalCategory(catKey)
    setModalOpen(true)
  }

  const getCounter = (catKey: BotCategoryKey) => {
    const catAction = categories[catKey] || 'charge'
    const catSigs = signatures.filter(s => s.category === catKey)
    const total = catSigs.length

    if (!masterEnabled || catAction === 'allow') {
      return { total, label: `${total} allowed`, className: 'xenarch-cat-stat--off' }
    }

    // Category is charge -- count overrides to allow.
    const allowedOverrides = catSigs.filter(s => overrides[s.name] === 'allow').length
    const gated = total - allowedOverrides

    if (gated === total) {
      return { total, label: `${total} gated`, className: 'xenarch-cat-stat--on' }
    }

    return {
      total,
      label: null,
      gatedCount: gated,
      totalLabel: `${total} bots`,
      gatedLabel: `${gated} gated`,
      className: 'xenarch-cat-stat--on',
    }
  }

  // Loading skeleton
  if (loading) {
    return (
      <div className="xenarch-section" style={{ borderTop: 'none' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
          <div>
            <div className="xenarch-skeleton" style={{ width: 120, height: 16, marginBottom: 6 }} />
            <div className="xenarch-skeleton" style={{ width: 280, height: 12 }} />
          </div>
          <div className="xenarch-skeleton" style={{ width: 44, height: 24, borderRadius: 12 }} />
        </div>
        {[0, 1, 2].map(i => (
          <div key={i} className="xenarch-cat-row" style={{ cursor: 'default' }}>
            <div className="xenarch-skeleton" style={{ width: 36, height: 20, borderRadius: 10 }} />
            <div className="xenarch-cat-info">
              <div className="xenarch-skeleton" style={{ width: 100, height: 13, marginBottom: 4 }} />
              <div className="xenarch-skeleton" style={{ width: 180, height: 13 }} />
            </div>
            <div className="xenarch-skeleton" style={{ width: 70, height: 11 }} />
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className="xenarch-section">
      {/* Master toggle */}
      <div className="xenarch-toggle-row">
        <div>
          <div className="xenarch-toggle-label">Gate AI bots</div>
          <div className="xenarch-toggle-description">
            All non-human traffic must pay unless allowed below
          </div>
        </div>
        <label className="xenarch-toggle">
          <input
            type="checkbox"
            checked={masterEnabled}
            onChange={handleMasterToggle}
            disabled={savingMaster}
          />
          <span className="xenarch-slider" />
        </label>
      </div>

      {/* Category rows */}
      {CATEGORIES.map(cat => {
        const catAction = categories[cat.key] || cat.defaultAction
        const isOn = masterEnabled && catAction === 'charge'
        const counter = getCounter(cat.key)
        const disabled = !masterEnabled
        const saving = savingCat === cat.key

        return (
          <div
            key={cat.key}
            className={`xenarch-cat-row${disabled ? ' xenarch-cat-row--disabled' : ''}`}
            onClick={() => !disabled && openModal(cat.key)}
          >
            <label
              className={`xenarch-cat-toggle${disabled ? ' xenarch-cat-toggle--disabled' : ''}`}
              onClick={e => e.stopPropagation()}
            >
              <input
                type="checkbox"
                checked={isOn}
                onChange={() => handleCategoryToggle(cat.key)}
                disabled={disabled || saving}
              />
              <span className="xenarch-cat-slider" />
            </label>
            <div className="xenarch-cat-info">
              <div className="xenarch-cat-name">{cat.name}</div>
              <div className="xenarch-cat-desc">{cat.description}</div>
            </div>
            {counter.label ? (
              <span className={`xenarch-cat-stat ${counter.className}`}>{counter.label}</span>
            ) : (
              <>
                <span className="xenarch-cat-stat xenarch-cat-stat--muted">{counter.totalLabel}</span>
                <span className={`xenarch-cat-stat ${counter.className}`}>{counter.gatedLabel}</span>
              </>
            )}
            <span className="xenarch-cat-chevron">&rsaquo;</span>
          </div>
        )
      })}

      <div className="xenarch-always-note">
        Search engines (Google, Bing) and social previews (Twitter, LinkedIn) always pass through free.
      </div>

      {modalOpen && (
        <BotDetailModal
          initialCategory={modalCategory}
          signatures={signatures}
          categories={categories}
          overrides={overrides}
          onOverrideChange={handleOverrideChange}
          onClose={() => setModalOpen(false)}
        />
      )}
    </div>
  )
}

import { useCallback, useState } from 'react'
import type { Features } from '../types'
import * as api from '../api'
import { FeatureRows, type FeatureKey } from './FeatureRows'

interface Props {
  features: Features
  onChange: (f: Features) => void
}

/**
 * XEN-684 — Features section. Saves on toggle and snaps back on failure,
 * like every other toggle in the plugin.
 */
export function FeaturesTab({ features, onChange }: Props) {
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const toggle = useCallback(
    async (key: FeatureKey) => {
      if (saving) return
      const prev = features
      const next = { ...features, [key]: !features[key] }
      onChange(next) // optimistic
      setSaving(true)
      setError(null)
      try {
        onChange(await api.saveFeatures({ [key]: next[key] }))
      } catch (e) {
        onChange(prev)
        setError(e instanceof Error ? e.message : 'Save failed.')
      }
      setSaving(false)
    },
    [features, saving, onChange],
  )

  return (
    <div className="section">
      <div className="section-head">
        <div className="section-title">Features</div>
        <div className="section-desc">
          Turn each part of Xenarch on or off. Turning one off keeps its settings for when you turn it back on.
        </div>
      </div>

      {error ? <div className="onboarding-error">{error}</div> : null}

      <FeatureRows
        gating={features.gating}
        checkout={features.checkout}
        wooActive={features.woo_active}
        busy={saving}
        onToggle={toggle}
      />

      {!features.gating && !features.checkout ? (
        <div className="muted">Both features are off. Xenarch does nothing on this site until you turn one on.</div>
      ) : null}
    </div>
  )
}

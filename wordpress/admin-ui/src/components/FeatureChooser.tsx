import { useCallback, useState } from 'react'
import type { Features } from '../types'
import * as api from '../api'
import { FeatureRows, type FeatureKey } from './FeatureRows'

interface Props {
  features: Features
  onSaved: (f: Features) => void
}

/** XEN-684 — first run: ask which features this site wants. */
export function FeatureChooser({ features, onSaved }: Props) {
  const [pick, setPick] = useState(() => ({
    gating: features.suggested.gating,
    checkout: features.suggested.checkout && features.woo_active,
  }))
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const toggle = useCallback((key: FeatureKey) => {
    setPick((p) => ({ ...p, [key]: !p[key] }))
    setError(null)
  }, [])

  const save = useCallback(async () => {
    setSaving(true)
    setError(null)
    try {
      onSaved(await api.saveFeatures(pick))
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not save.')
      setSaving(false)
    }
  }, [pick, onSaved])

  return (
    <div className="onboarding">
      <div className="onboarding-title">What do you want Xenarch to do on this site?</div>
      <div className="onboarding-desc">Pick one or both. You can change this any time under Features.</div>

      {error ? <div className="onboarding-error">{error}</div> : null}

      <FeatureRows
        gating={pick.gating}
        checkout={pick.checkout}
        wooActive={features.woo_active}
        busy={saving}
        onToggle={toggle}
      />

      <div>
        <button className="btn primary" onClick={save} disabled={saving || (!pick.gating && !pick.checkout)}>
          {saving ? 'Saving…' : 'Continue'}
        </button>
      </div>
    </div>
  )
}

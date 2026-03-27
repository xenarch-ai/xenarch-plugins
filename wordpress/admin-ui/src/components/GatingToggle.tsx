import { useState } from 'react'
import type { Settings, SettingsChangeHandler } from '../types'
import * as api from '../api'

interface Props {
  settings: Settings
  onSettingsChange: SettingsChangeHandler
}

export function GatingToggle({ settings, onSettingsChange }: Props) {
  const [saving, setSaving] = useState(false)

  const handleToggle = async () => {
    const newValue = settings.gate_unknown_traffic === '1' ? '0' : '1'
    setSaving(true)
    try {
      const updated = await api.updateSettings({ xenarch_gate_unknown_traffic: newValue })
      onSettingsChange(updated)
    } catch {
      // Revert on failure — settings stay the same
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="xenarch-card">
      <h3>Additional Gating</h3>
      <div className="xenarch-toggle-row">
        <label className="xenarch-toggle">
          <input
            type="checkbox"
            checked={settings.gate_unknown_traffic === '1'}
            onChange={handleToggle}
            disabled={saving}
          />
          <span className="xenarch-slider" />
        </label>
        <div>
          <div className="xenarch-toggle-label">Gate unknown traffic</div>
          <div className="xenarch-toggle-description">
            In addition to the {settings.bot_signature_count} known bot signatures always gated by Xenarch,
            this option also gates requests from unrecognized User-Agents. Disable if third-party
            webhooks (Stripe, Slack, etc.) are being incorrectly gated.
          </div>
        </div>
      </div>
    </div>
  )
}

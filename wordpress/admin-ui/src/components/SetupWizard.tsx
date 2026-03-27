import { useState } from 'react'
import type { Settings, SettingsChangeHandler } from '../types'
import * as api from '../api'

interface Props {
  settings: Settings
  onSettingsChange: SettingsChangeHandler
}

export function SetupWizard({ settings, onSettingsChange }: Props) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      const updated = await api.register(email, password)
      onSettingsChange(updated)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed')
    } finally {
      setLoading(false)
    }
  }

  const handleAddSite = async () => {
    setLoading(true)
    setError(null)
    try {
      const updated = await api.addSite()
      onSettingsChange(updated)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to add site')
    } finally {
      setLoading(false)
    }
  }

  return (
    <>
      {error && (
        <div className="xenarch-notice xenarch-notice--error">{error}</div>
      )}

      {!settings.is_registered ? (
        <div className="xenarch-card">
          <h3>
            <span className="xenarch-step-number">1</span>
            Register
          </h3>
          <p>Create a Xenarch publisher account to get started.</p>
          <form onSubmit={handleRegister}>
            <div className="xenarch-field">
              <label htmlFor="xen-email">Email</label>
              <input
                id="xen-email"
                type="email"
                className="xenarch-input"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="xenarch-field">
              <label htmlFor="xen-password">Password</label>
              <input
                id="xen-password"
                type="password"
                className="xenarch-input"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength={8}
              />
            </div>
            <button type="submit" className="xenarch-btn xenarch-btn--primary" disabled={loading}>
              {loading && <span className="xenarch-spinner" />}
              Register
            </button>
          </form>
        </div>
      ) : !settings.has_site ? (
        <div className="xenarch-card">
          <h3>
            <span className="xenarch-step-number">2</span>
            Add Your Site
          </h3>
          <p>
            We detected your domain as <strong>{settings.domain}</strong>. Click below to register it with Xenarch.
          </p>
          <button
            className="xenarch-btn xenarch-btn--primary"
            onClick={handleAddSite}
            disabled={loading}
          >
            {loading && <span className="xenarch-spinner" />}
            Add Site
          </button>
        </div>
      ) : null}
    </>
  )
}

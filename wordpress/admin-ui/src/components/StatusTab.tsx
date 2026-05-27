import { useState, useCallback } from 'react'
import type { Settings } from '../types'
import * as api from '../api'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
}

export function StatusTab({ settings, onSettingsChange }: Props) {
  const connected = settings.has_site
  const tokenPreview = settings.site_token
    ? `${settings.site_token.slice(0, 8)}...${settings.site_token.slice(-4)}`
    : ''

  const [disconnecting, setDisconnecting] = useState(false)
  const [disconnectError, setDisconnectError] = useState('')

  const disconnect = useCallback(async () => {
    if (disconnecting) return
    setDisconnecting(true)
    setDisconnectError('')
    try {
      const updated = await api.disconnect()
      onSettingsChange(updated)
    } catch (err) {
      setDisconnectError(err instanceof Error ? err.message : 'Could not disconnect.')
      setDisconnecting(false)
    }
  }, [disconnecting, onSettingsChange])

  return (
    <div className="xn-status-card">
      <div className="xn-data-row">
        <span className="xn-data-key">status</span>
        <span className="xn-data-val">
          <span
            className={`xn-status-pill ${connected ? 'xn-status-pill--ok' : 'xn-status-pill--err'}`}
          >
            <span className="xn-status-pill-dot" />
            {connected ? 'connected' : 'disconnected'}
          </span>
        </span>
      </div>

      <div className="xn-data-row">
        <span className="xn-data-key">site token</span>
        <span className="xn-data-val">
          {connected ? tokenPreview : <span className="xn-dash">&mdash;</span>}
        </span>
      </div>

      <div className="xn-data-row">
        <span className="xn-data-key">domain</span>
        <span className="xn-data-val">{settings.domain || <span className="xn-dash">&mdash;</span>}</span>
      </div>

      <div className="xn-data-row">
        <span className="xn-data-key">server-side</span>
        <span className="xn-data-val">
          {connected ? (
            <>
              <span className="xn-dot xn-dot--green" />
              Active &mdash; {settings.bot_signature_count} bot signatures
            </>
          ) : (
            <>
              <span className="xn-dot xn-dot--red" />
              Inactive
            </>
          )}
        </span>
      </div>

      <div className="xn-data-row">
        <span className="xn-data-key">pay.json</span>
        <span className="xn-data-val">
          {connected && settings.pay_json_url ? (
            <a href={settings.pay_json_url} target="_blank" rel="noopener noreferrer">
              {settings.pay_json_url}
            </a>
          ) : (
            <span className="xn-dash">&mdash;</span>
          )}
        </span>
      </div>

      <div className="xn-data-row xn-data-row--last">
        <span className="xn-data-key">xenarch.md</span>
        <span className="xn-data-val">
          {connected && settings.xenarch_md_url ? (
            <a href={settings.xenarch_md_url} target="_blank" rel="noopener noreferrer">
              {settings.xenarch_md_url}
            </a>
          ) : (
            <span className="xn-dash">&mdash;</span>
          )}
        </span>
      </div>

      {connected ? (
        <div style={{ marginTop: '1.5rem', paddingTop: '1rem', borderTop: '1px solid var(--border, #eee)' }}>
          <button
            className="xenarch-btn"
            onClick={disconnect}
            disabled={disconnecting}
          >
            {disconnecting ? 'Disconnecting…' : 'Disconnect from Xenarch'}
          </button>
          {disconnectError ? (
            <div className="xenarch-onboarding-error" style={{ marginTop: '0.75rem' }}>
              {disconnectError}
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}

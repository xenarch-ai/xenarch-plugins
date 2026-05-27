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
    ? `${settings.site_token.slice(0, 8)}…${settings.site_token.slice(-4)}`
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
    <>
      <div className="status-card">
        <div className="data-row">
          <span className="data-key">status</span>
          <span className="data-val">
            <span className={`status-pill ${connected ? 'ok' : 'err'}`}>
              <span className="pill-dot" />
              {connected ? 'connected' : 'disconnected'}
            </span>
          </span>
        </div>

        <div className="data-row">
          <span className="data-key">site token</span>
          <span className="data-val">
            {connected ? tokenPreview : <span className="muted">—</span>}
          </span>
        </div>

        <div className="data-row">
          <span className="data-key">domain</span>
          <span className="data-val">{settings.domain || <span className="muted">—</span>}</span>
        </div>

        <div className="data-row">
          <span className="data-key">server-side</span>
          <span className="data-val">
            {connected ? (
              <>
                <span
                  className="dot-ok"
                  style={{ width: 6, height: 6, borderRadius: '50%', display: 'inline-block' }}
                />
                Active — {settings.bot_signature_count} bot signatures
              </>
            ) : (
              <>
                <span
                  className="dot-err"
                  style={{ width: 6, height: 6, borderRadius: '50%', display: 'inline-block' }}
                />
                Inactive
              </>
            )}
          </span>
        </div>

        <div className="data-row">
          <span className="data-key">pay.json</span>
          <span className="data-val">
            {connected && settings.pay_json_url ? (
              <a href={settings.pay_json_url} target="_blank" rel="noopener noreferrer">
                {settings.pay_json_url}
              </a>
            ) : (
              <span className="muted">—</span>
            )}
          </span>
        </div>

        <div className="data-row">
          <span className="data-key">xenarch.md</span>
          <span className="data-val">
            {connected && settings.xenarch_md_url ? (
              <a href={settings.xenarch_md_url} target="_blank" rel="noopener noreferrer">
                {settings.xenarch_md_url}
              </a>
            ) : (
              <span className="muted">—</span>
            )}
          </span>
        </div>
      </div>

      {connected ? (
        <div className="actions" style={{ marginTop: 24 }}>
          <button className="btn secondary" onClick={disconnect} disabled={disconnecting}>
            {disconnecting ? 'Disconnecting…' : 'Disconnect from Xenarch'}
          </button>
          {disconnectError ? <span className="muted">{disconnectError}</span> : null}
        </div>
      ) : null}
    </>
  )
}

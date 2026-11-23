import { useCallback, useEffect, useState } from 'react'
import type { WooStatus } from '../types'
import * as api from '../api'

// XEN-681 — WooCommerce "Crypto: USDC" checkout settings. The merchant key
// and the webhook secret stay in PHP; this tab only ever sees previews.

function short(addr: string): string {
  return addr ? `${addr.slice(0, 6)}…${addr.slice(-4)}` : ''
}

export function CheckoutTab() {
  const [woo, setWoo] = useState<WooStatus | null>(null)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [key, setKey] = useState('')
  const [link, setLink] = useState('')
  const [connecting, setConnecting] = useState(false)
  const [connectMsg, setConnectMsg] = useState<string | null>(null)
  const [editing, setEditing] = useState(false)

  const [title, setTitle] = useState('')
  const [titleDirty, setTitleDirty] = useState(false)
  const [saving, setSaving] = useState(false)
  const [saveMsg, setSaveMsg] = useState<string | null>(null)
  const [disconnecting, setDisconnecting] = useState(false)

  const apply = useCallback((w: WooStatus) => {
    setWoo(w)
    setTitle(w.title)
    setTitleDirty(false)
  }, [])

  useEffect(() => {
    api
      .fetchWoo()
      .then(apply)
      .catch((e) => setLoadError(e instanceof Error ? e.message : 'Could not load checkout settings.'))
  }, [apply])

  const connect = useCallback(async () => {
    setConnecting(true)
    setConnectMsg(null)
    try {
      const w = await api.connectWoo({ merchant_key: key.trim(), link: link.trim() })
      apply(w)
      setKey('')
      setLink('')
      setEditing(false)
    } catch (e) {
      setConnectMsg(e instanceof Error ? e.message : 'Could not connect.')
    }
    setConnecting(false)
  }, [key, link, apply])

  const toggleEnabled = useCallback(async () => {
    if (!woo || !woo.connected || saving) return
    const next = !woo.enabled
    setWoo({ ...woo, enabled: next }) // optimistic; snaps back on failure
    setSaving(true)
    setSaveMsg(null)
    try {
      apply(await api.saveWoo({ enabled: next }))
    } catch (e) {
      setWoo({ ...woo, enabled: !next })
      setSaveMsg(e instanceof Error ? e.message : 'Save failed.')
    }
    setSaving(false)
  }, [woo, saving, apply])

  const saveTitle = useCallback(async () => {
    setSaving(true)
    setSaveMsg(null)
    try {
      apply(await api.saveWoo({ title: title.trim() }))
      setSaveMsg('Saved.')
    } catch (e) {
      setSaveMsg(e instanceof Error ? e.message : 'Save failed.')
    }
    setSaving(false)
  }, [title, apply])

  const disconnect = useCallback(async () => {
    setDisconnecting(true)
    setSaveMsg(null)
    try {
      apply(await api.disconnectWoo())
    } catch (e) {
      setSaveMsg(e instanceof Error ? e.message : 'Could not disconnect.')
    }
    setDisconnecting(false)
  }, [apply])

  if (loadError) return <div className="onboarding-error">{loadError}</div>
  if (!woo) return <div className="empty">Loading checkout settings…</div>

  if (!woo.woo_active) {
    return (
      <div className="section">
        <div className="section-head">
          <div className="section-title">Checkout</div>
          <div className="section-desc">Install and activate WooCommerce to take USDC payments at checkout.</div>
        </div>
      </div>
    )
  }

  const dash = woo.dashboard_url
  const showForm = !woo.connected || editing

  return (
    <>
      {/* Connection */}
      <div className="section">
        <div className="section-head">
          <div className="section-title">Crypto checkout</div>
          <div className="section-desc">
            Buyers pay the order total in USDC on Base from their own wallet, straight to your wallet.
            The order moves to Processing on its own when the payment lands.
          </div>
        </div>

        {!woo.currency_ok ? (
          <div className="onboarding-error" style={{ marginBottom: 16 }}>
            Your store currency is {woo.currency || 'not set'}. USDC is a dollar token, so the option only
            shows when the store currency is USD.
          </div>
        ) : null}

        {woo.connected ? (
          <div className="status-card" style={{ marginBottom: showForm ? 20 : 0 }}>
            <div className="data-row">
              <span className="data-key">status</span>
              <span className="data-val">
                <span className="status-pill ok">
                  <span className="pill-dot" />
                  connected
                </span>
              </span>
            </div>
            <div className="data-row">
              <span className="data-key">pay link</span>
              <span className="data-val">
                <a href={woo.link_url} target="_blank" rel="noopener noreferrer">{woo.link_url}</a>
              </span>
            </div>
            <div className="data-row">
              <span className="data-key">pays to</span>
              <span className="data-val">{short(woo.pay_to)} · USDC on Base</span>
            </div>
            <div className="data-row">
              <span className="data-key">merchant key</span>
              <span className="data-val">{woo.key_preview}</span>
            </div>
            <div className="data-row">
              <span className="data-key">webhook</span>
              <span className="data-val">{woo.webhook_url}</span>
            </div>
          </div>
        ) : null}

        {showForm ? (
          <>
            {!woo.connected ? (
              <ol className="woo-steps">
                <li>
                  In the Xenarch dashboard, create a <strong>checkout</strong> pay link with an{' '}
                  <strong>open amount</strong>, reusable, no return URL.{' '}
                  <a href={`${dash}/payments/new`} target="_blank" rel="noopener noreferrer">Create link →</a>
                </li>
                <li>
                  Create a merchant key on your profile.{' '}
                  <a href={`${dash}/profile`} target="_blank" rel="noopener noreferrer">Profile →</a>
                </li>
                <li>Paste both below. Use this link for this store only: Xenarch sends its payments here.</li>
              </ol>
            ) : null}
            <div className="row">
              <label htmlFor="xn-woo-key">Merchant key</label>
              <input
                id="xn-woo-key"
                type="password"
                className="wide"
                autoComplete="off"
                placeholder={woo.connected ? 'Leave empty to keep the current key' : 'xm_live_…'}
                value={key}
                onChange={(e) => { setKey(e.target.value); setConnectMsg(null) }}
              />
            </div>
            <div className="row">
              <label htmlFor="xn-woo-link">Pay link</label>
              <input
                id="xn-woo-link"
                type="text"
                className="wide"
                placeholder="https://pay.xenarch.com/l/…"
                value={link}
                onChange={(e) => { setLink(e.target.value); setConnectMsg(null) }}
                onKeyDown={(e) => { if (e.key === 'Enter' && link.trim()) void connect() }}
              />
            </div>
            <div className="actions">
              <button
                className="btn primary"
                onClick={connect}
                disabled={connecting || !link.trim() || (!woo.connected && !key.trim())}
              >
                {connecting ? 'Connecting…' : woo.connected ? 'Switch link' : 'Connect'}
              </button>
              {woo.connected ? (
                <button className="btn secondary" onClick={() => { setEditing(false); setConnectMsg(null) }}>
                  Cancel
                </button>
              ) : null}
              {connectMsg ? <span className="muted">{connectMsg}</span> : null}
            </div>
          </>
        ) : (
          <div className="actions">
            <button className="btn secondary" onClick={() => setEditing(true)}>Change link or key</button>
            <button className="btn secondary" onClick={disconnect} disabled={disconnecting}>
              {disconnecting ? 'Disconnecting…' : 'Disconnect'}
            </button>
          </div>
        )}
      </div>

      {/* Option at checkout */}
      {woo.connected ? (
        <div className="section">
          <div className="section-head">
            <div className="section-title">At checkout</div>
            <div className="section-desc">How the option appears to buyers.</div>
          </div>

          <div className="master">
            <div className="left">
              <div className="master-label">Offer USDC at checkout</div>
              <div className="master-desc">Shows the option on the WooCommerce checkout page.</div>
            </div>
            <div
              className={`toggle${woo.enabled ? '' : ' off'}`}
              role="switch"
              aria-checked={woo.enabled}
              tabIndex={0}
              onClick={toggleEnabled}
              onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); void toggleEnabled() } }}
            >
              <div className="dot" />
            </div>
          </div>

          <div className="row">
            <label htmlFor="xn-woo-title">Label</label>
            <input
              id="xn-woo-title"
              type="text"
              className="wide"
              maxLength={80}
              value={title}
              onChange={(e) => { setTitle(e.target.value); setTitleDirty(true); setSaveMsg(null) }}
              onKeyDown={(e) => { if (e.key === 'Enter' && titleDirty && title.trim()) void saveTitle() }}
            />
          </div>
          <div className="actions">
            <button className="btn primary" onClick={saveTitle} disabled={!titleDirty || !title.trim() || saving}>
              {saving ? 'Saving…' : 'Save label'}
            </button>
            {saveMsg ? <span className="muted">{saveMsg}</span> : null}
          </div>

          <div className="woo-note">
            Your logo and brand colour on the pay page come from your Xenarch profile.{' '}
            <a href={`${dash}/profile`} target="_blank" rel="noopener noreferrer">Edit branding →</a>
          </div>
        </div>
      ) : null}
    </>
  )
}

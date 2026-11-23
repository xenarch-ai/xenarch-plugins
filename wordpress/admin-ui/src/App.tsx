import { useState, useCallback, useEffect } from 'react'
import type { Settings } from './types'
import * as api from './api'
import { Onboarding } from './components/Onboarding'
import { SettingsTab } from './components/SettingsTab'
import { EarningsTab } from './components/EarningsTab'
import { StatusTab } from './components/StatusTab'
import { CheckoutTab } from './components/CheckoutTab'

type Tab = 'earnings' | 'settings' | 'checkout' | 'status'

// XEN-681: the Checkout tab exists only with WooCommerce active. It doesn't
// need the bot-gating site connection, so it's reachable before onboarding.
const WOO_ACTIVE = Boolean(window.xenarchAdmin.wooActive)

function initialTab(): Tab {
  const t = new URLSearchParams(window.location.search).get('tab')
  if (t === 'checkout' && WOO_ACTIVE) return 'checkout'
  if (t === 'settings' || t === 'status') return t
  return 'earnings'
}

type ClaimState =
  | { kind: 'idle' }
  | { kind: 'exchanging' }
  | { kind: 'error'; message: string }

function stripClaimTokenFromUrl(): string {
  const url = new URL(window.location.href)
  url.searchParams.delete('claim_token')
  // Preserve `?page=xenarch` and anything else the WP admin needs.
  return url.toString()
}

export function App() {
  const [activeTab, setActiveTab] = useState<Tab>(initialTab)
  const [settings, setSettings] = useState<Settings>(window.xenarchAdmin.settings)
  const [claim, setClaim] = useState<ClaimState>({ kind: 'idle' })

  // Apply initial theme.
  useEffect(() => {
    const stored = localStorage.getItem('xenarch-theme')
    const theme = (stored === 'light' || stored === 'dark')
      ? stored
      : window.matchMedia?.('(prefers-color-scheme: light)').matches ? 'light' : 'dark'
    const root = document.getElementById('xenarch-admin')
    if (root) root.setAttribute('data-theme', theme)
    document.body.classList.toggle('xenarch-light', theme === 'light')
  }, [])

  // Claim handshake: if the dashboard redirected back with ?claim_token=…
  // in the URL, swap it for the persistent site_token before doing
  // anything else. Always strip the token from the URL afterwards
  // (success or failure) so a browser refresh doesn't replay.
  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const claimToken = params.get('claim_token')
    if (!claimToken) return

    setClaim({ kind: 'exchanging' })
    api
      .exchangeClaim(claimToken)
      .then((updated) => {
        setSettings(updated)
        setClaim({ kind: 'idle' })
        window.history.replaceState({}, '', stripClaimTokenFromUrl())
      })
      .catch((err) => {
        const message = err instanceof Error ? err.message : 'Could not finish connecting.'
        setClaim({ kind: 'error', message })
        window.history.replaceState({}, '', stripClaimTokenFromUrl())
      })
  }, [])

  const onSettingsChange = useCallback((updated: Settings) => {
    setSettings(updated)
  }, [])

  if (claim.kind === 'exchanging') {
    return (
      <div className="xenarch-app">
        <div className="onboarding">
          <div className="onboarding-title">Finishing the connection…</div>
          <div className="onboarding-desc">Pairing this site with your Xenarch account.</div>
        </div>
      </div>
    )
  }

  if (!settings.has_site) {
    const onboarding = (
      <Onboarding
        domain={settings.domain}
        pluginAdminUrl={window.location.href.split('?')[0] + '?page=xenarch'}
        errorMessage={claim.kind === 'error' ? claim.message : null}
      />
    )
    if (!WOO_ACTIVE) {
      return <div className="xenarch-app">{onboarding}</div>
    }
    const showCheckout = activeTab === 'checkout'
    return (
      <div className="xenarch-app">
        <div className="xenarch-tabs-row">
          <button
            className={`xenarch-tab ${!showCheckout ? 'xenarch-tab--active' : ''}`}
            onClick={() => setActiveTab('earnings')}
          >
            Bot gating
          </button>
          <button
            className={`xenarch-tab ${showCheckout ? 'xenarch-tab--active' : ''}`}
            onClick={() => setActiveTab('checkout')}
          >
            Checkout
          </button>
        </div>
        <div className="xenarch-tab-content">{showCheckout ? <CheckoutTab /> : onboarding}</div>
      </div>
    )
  }

  return (
    <div className="xenarch-app">
      <div className="xenarch-tabs-row">
        <button
          className={`xenarch-tab ${activeTab === 'earnings' ? 'xenarch-tab--active' : ''}`}
          onClick={() => setActiveTab('earnings')}
        >
          Earnings
        </button>
        <button
          className={`xenarch-tab ${activeTab === 'settings' ? 'xenarch-tab--active' : ''}`}
          onClick={() => setActiveTab('settings')}
        >
          Settings
        </button>
        {WOO_ACTIVE ? (
          <button
            className={`xenarch-tab ${activeTab === 'checkout' ? 'xenarch-tab--active' : ''}`}
            onClick={() => setActiveTab('checkout')}
          >
            Checkout
          </button>
        ) : null}
        <button
          className={`xenarch-tab ${activeTab === 'status' ? 'xenarch-tab--active' : ''}`}
          onClick={() => setActiveTab('status')}
        >
          Status
        </button>
      </div>

      <div className="xenarch-tab-content">
        {activeTab === 'earnings' && <EarningsTab settings={settings} />}
        {activeTab === 'settings' && <SettingsTab settings={settings} onSettingsChange={onSettingsChange} />}
        {activeTab === 'checkout' && WOO_ACTIVE && <CheckoutTab />}
        {activeTab === 'status' && <StatusTab settings={settings} onSettingsChange={onSettingsChange} />}
      </div>

      <div className="xenarch-footer">
        <span>&copy; {new Date().getFullYear()} Xenarch</span>
        <span>support@xenarch.com</span>
      </div>
    </div>
  )
}

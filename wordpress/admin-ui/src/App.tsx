import { useState, useCallback, useEffect } from 'react'
import type { Settings } from './types'
import * as api from './api'
import { Onboarding } from './components/Onboarding'
import { SettingsTab } from './components/SettingsTab'
import { EarningsTab } from './components/EarningsTab'
import { StatusTab } from './components/StatusTab'

type Tab = 'earnings' | 'settings' | 'status'

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
  const [activeTab, setActiveTab] = useState<Tab>('earnings')
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
        <div className="xenarch-onboarding">
          <div className="xenarch-onboarding-title">Finishing the connection…</div>
          <div className="xenarch-onboarding-desc">Pairing this site with your Xenarch account.</div>
        </div>
      </div>
    )
  }

  if (!settings.has_site) {
    return (
      <div className="xenarch-app">
        {claim.kind === 'error' ? (
          <div className="xenarch-onboarding-error" style={{ marginBottom: '1rem' }}>
            {claim.message}
          </div>
        ) : null}
        <Onboarding domain={settings.domain} pluginAdminUrl={window.location.href.split('?')[0] + '?page=xenarch'} />
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
        {activeTab === 'status' && <StatusTab settings={settings} onSettingsChange={onSettingsChange} />}
      </div>

      <div className="xenarch-footer">
        <span>&copy; {new Date().getFullYear()} Xenarch</span>
        <span>support@xenarch.com</span>
      </div>
    </div>
  )
}

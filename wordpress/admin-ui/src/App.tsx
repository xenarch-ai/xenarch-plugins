import { useState, useCallback, useEffect } from 'react'
import type { Settings, Features } from './types'
import * as api from './api'
import { Onboarding } from './components/Onboarding'
import { SettingsTab } from './components/SettingsTab'
import { EarningsTab } from './components/EarningsTab'
import { StatusTab } from './components/StatusTab'
import { CheckoutTab } from './components/CheckoutTab'
import { FeaturesTab } from './components/FeaturesTab'
import { FeatureChooser } from './components/FeatureChooser'

// XEN-684: which tabs show depends on the Features switch. `gating` is the
// connect-this-site screen, shown in place of Earnings/Settings/Status
// while bot gating is on but the site isn't paired yet. The Checkout tab
// needs WooCommerce (XEN-681) and the checkout feature on.
type Tab = 'earnings' | 'settings' | 'gating' | 'checkout' | 'status' | 'features'

const TAB_LABELS: Record<Tab, string> = {
  earnings: 'Earnings',
  settings: 'Settings',
  gating: 'Bot gating',
  checkout: 'Checkout',
  status: 'Status',
  features: 'Features',
}

function visibleTabs(features: Features, hasSite: boolean): Tab[] {
  const tabs: Tab[] = []
  if (features.gating) tabs.push(...(hasSite ? (['earnings', 'settings'] as Tab[]) : (['gating'] as Tab[])))
  if (features.checkout) tabs.push('checkout')
  if (features.gating && hasSite) tabs.push('status')
  tabs.push('features')
  return tabs
}

function requestedTab(): Tab | null {
  const t = new URLSearchParams(window.location.search).get('tab')
  return t && Object.prototype.hasOwnProperty.call(TAB_LABELS, t) ? (t as Tab) : null
}

function setTagline(text: string) {
  const el = document.getElementById('xenarch-page-subtitle')
  if (el && text) el.textContent = text
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
  const [activeTab, setActiveTab] = useState<Tab | null>(requestedTab)
  const [settings, setSettings] = useState<Settings>(window.xenarchAdmin.settings)
  const [features, setFeatures] = useState<Features>(window.xenarchAdmin.features)
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

  const onFeaturesChange = useCallback((updated: Features) => {
    setFeatures(updated)
    setTagline(updated.tagline)
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

  if (!features.chosen) {
    return (
      <div className="xenarch-app">
        <FeatureChooser features={features} onSaved={onFeaturesChange} />
      </div>
    )
  }

  const tabs = visibleTabs(features, settings.has_site)
  const tab: Tab = activeTab && tabs.includes(activeTab) ? activeTab : tabs[0]

  return (
    <div className="xenarch-app">
      <div className="xenarch-tabs-row">
        {tabs.map((t) => (
          <button
            key={t}
            className={`xenarch-tab ${tab === t ? 'xenarch-tab--active' : ''}`}
            onClick={() => setActiveTab(t)}
          >
            {TAB_LABELS[t]}
          </button>
        ))}
      </div>

      <div className="xenarch-tab-content">
        {tab === 'gating' && (
          <Onboarding
            domain={settings.domain}
            pluginAdminUrl={window.location.href.split('?')[0] + '?page=xenarch'}
            errorMessage={claim.kind === 'error' ? claim.message : null}
          />
        )}
        {tab === 'earnings' && <EarningsTab settings={settings} />}
        {tab === 'settings' && <SettingsTab settings={settings} onSettingsChange={onSettingsChange} />}
        {tab === 'checkout' && <CheckoutTab />}
        {tab === 'status' && <StatusTab settings={settings} onSettingsChange={onSettingsChange} />}
        {tab === 'features' && <FeaturesTab features={features} onChange={onFeaturesChange} />}
      </div>

      <div className="xenarch-footer">
        <span>&copy; {new Date().getFullYear()} Xenarch</span>
        <span>support@xenarch.com</span>
      </div>
    </div>
  )
}

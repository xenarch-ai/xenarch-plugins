import { useState, useCallback, useEffect } from 'react'
import type { Settings } from './types'
import { Onboarding } from './components/Onboarding'
import { SettingsTab } from './components/SettingsTab'
import { EarningsTab } from './components/EarningsTab'
import { StatusTab } from './components/StatusTab'

type Tab = 'earnings' | 'settings' | 'status'

export function App() {
  const [activeTab, setActiveTab] = useState<Tab>('settings')
  const [settings, setSettings] = useState<Settings>(window.xenarchAdmin.settings)

  // Apply initial theme from localStorage.
  useEffect(() => {
    const theme = localStorage.getItem('xenarch-theme') || 'dark'
    const root = document.getElementById('xenarch-admin')
    if (root) root.setAttribute('data-theme', theme)
    document.body.classList.toggle('xenarch-light', theme === 'light')
  }, [])

  const onSettingsChange = useCallback((updated: Settings) => {
    setSettings(updated)
  }, [])

  const hasWallet = settings.has_wallet

  // No wallet = onboarding only.
  if (!hasWallet) {
    return (
      <div className="xenarch-app">
        <Onboarding settings={settings} onSettingsChange={onSettingsChange} />
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
        {activeTab === 'status' && <StatusTab settings={settings} />}
      </div>

      <div className="xenarch-footer">
        <span>&copy; {new Date().getFullYear()} Xenarch</span>
        <span>support@xenarch.com</span>
      </div>
    </div>
  )
}

import { useState, useCallback } from 'react'
import type { Settings } from './types'
import { SettingsTab } from './components/SettingsTab'
import { EarningsTab } from './components/EarningsTab'

type Tab = 'settings' | 'earnings'

export function App() {
  const [activeTab, setActiveTab] = useState<Tab>('settings')
  const [settings, setSettings] = useState<Settings>(window.xenarchAdmin.settings)

  const onSettingsChange = useCallback((updated: Settings) => {
    setSettings(updated)
  }, [])

  return (
    <div className="xenarch-app">
      <div className="xenarch-tabs">
        <button
          className={`xenarch-tab ${activeTab === 'settings' ? 'xenarch-tab--active' : ''}`}
          onClick={() => setActiveTab('settings')}
        >
          Settings
        </button>
        <button
          className={`xenarch-tab ${activeTab === 'earnings' ? 'xenarch-tab--active' : ''}`}
          onClick={() => setActiveTab('earnings')}
          disabled={!settings.has_site}
        >
          Earnings
        </button>
      </div>

      <div className="xenarch-tab-content">
        {activeTab === 'settings' && (
          <SettingsTab settings={settings} onSettingsChange={onSettingsChange} />
        )}
        {activeTab === 'earnings' && settings.has_site && (
          <EarningsTab settings={settings} />
        )}
      </div>
    </div>
  )
}

import { useState, useCallback, useEffect } from 'react'
import type { Settings, SettingsChangeHandler } from './types'
import { SettingsTab } from './components/SettingsTab'
import { EarningsTab } from './components/EarningsTab'
import { ThemeToggle } from './components/ThemeToggle'
import { initAppKit } from './wallet/config'

type Tab = 'settings' | 'earnings'
type Theme = 'dark' | 'light'

// Initialise AppKit at module level (before first render).
const wcProjectId = window.xenarchAdmin?.wcProjectId || ''
if (wcProjectId) {
  initAppKit(wcProjectId)
}

function getInitialTheme(): Theme {
  const stored = localStorage.getItem('xenarch-theme')
  if (stored === 'light' || stored === 'dark') return stored
  // Auto-detect system preference on first visit.
  if (window.matchMedia?.('(prefers-color-scheme: light)').matches) return 'light'
  return 'dark'
}

export function App() {
  const [activeTab, setActiveTab] = useState<Tab>('earnings')
  const [settings, setSettings] = useState<Settings>(window.xenarchAdmin.settings)
  const [theme, setTheme] = useState<Theme>(getInitialTheme)

  useEffect(() => {
    const root = document.getElementById('xenarch-admin')
    if (root) root.setAttribute('data-theme', theme)
    localStorage.setItem('xenarch-theme', theme)

    const wpContent = document.getElementById('wpcontent')
    const wpTitle = document.querySelector('.wrap > h1') as HTMLElement
    if (wpContent) {
      wpContent.style.background = theme === 'dark' ? '#1a1a1a' : ''
    }
    if (wpTitle) {
      wpTitle.style.color = theme === 'dark' ? '#f0f0f0' : ''
    }
    return () => {
      if (wpContent) wpContent.style.background = ''
      if (wpTitle) wpTitle.style.color = ''
    }
  }, [theme])

  const toggleTheme = useCallback(() => {
    setTheme((t) => (t === 'dark' ? 'light' : 'dark'))
  }, [])

  const onSettingsChange = useCallback<SettingsChangeHandler>((updated) => {
    setSettings(updated)
  }, [])

  return (
    <div className="xenarch-app">
      <div className="xenarch-header">
        <div className="xenarch-tabs">
          <button
            className={`xenarch-tab ${activeTab === 'earnings' ? 'xenarch-tab--active' : ''}`}
            onClick={() => setActiveTab('earnings')}
            disabled={!settings.has_site}
          >
            Earnings
          </button>
          <button
            className={`xenarch-tab ${activeTab === 'settings' ? 'xenarch-tab--active' : ''}`}
            onClick={() => setActiveTab('settings')}
          >
            Settings
          </button>
        </div>
        <div className="xenarch-header-logo">
          <svg width="28" height="28" viewBox="0 0 120 120">
            <line x1="28" y1="28" x2="60" y2="60" stroke="var(--xn-border-hover)" strokeWidth="1.5"/>
            <line x1="92" y1="28" x2="60" y2="60" stroke="var(--xn-border-hover)" strokeWidth="1.5"/>
            <line x1="28" y1="92" x2="60" y2="60" stroke="var(--xn-border-hover)" strokeWidth="1.5"/>
            <line x1="92" y1="92" x2="60" y2="60" stroke="var(--xn-border-hover)" strokeWidth="1.5"/>
            <circle cx="28" cy="28" r="6" fill="var(--xn-text)"/>
            <circle cx="92" cy="28" r="6" fill="var(--xn-text)"/>
            <circle cx="28" cy="92" r="6" fill="var(--xn-text)"/>
            <circle cx="92" cy="92" r="6" fill="var(--xn-text)"/>
            <circle cx="60" cy="60" r="8" fill="var(--xn-text)"/>
          </svg>
          <span className="xenarch-header-wordmark">xenarch</span>
        </div>
        <ThemeToggle theme={theme} onToggle={toggleTheme} />
      </div>

      <div className="xenarch-tab-content">
        {activeTab === 'settings' && (
          <SettingsTab settings={settings} onSettingsChange={onSettingsChange} />
        )}
        {activeTab === 'earnings' && settings.has_site && (
          <EarningsTab settings={settings} />
        )}
      </div>

      <div className="xenarch-footer">
        <span>&nbsp;<span style={{ fontSize: '13px', verticalAlign: '-2px' }}>&#169;</span> Xenarch. {new Date().getFullYear()}</span>
        <span>v{window.xenarchAdmin.version}&nbsp;</span>
      </div>
    </div>
  )
}

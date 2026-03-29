import { createAppKit } from '@reown/appkit'
import { WagmiAdapter } from '@reown/appkit-adapter-wagmi'
import { base, mainnet } from '@reown/appkit/networks'

type AppKit = ReturnType<typeof createAppKit>

let appKitInstance: AppKit | null = null

const metadata = {
  name: 'Xenarch',
  description: 'Xenarch WordPress Plugin - Payout Wallet',
  url: window.location.origin,
  icons: [],
}

const DARK_THEME = {
  '--apkt-font-family': 'Inter, system-ui, sans-serif',
  '--apkt-accent': '#4ade80',
  '--apkt-color-mix': '#0a0a0a',
  '--apkt-color-mix-strength': 25,
  '--apkt-border-radius-master': 2,
  '--apkt-z-index': 100000,
} as Record<string, string | number>

const LIGHT_THEME = {
  '--apkt-font-family': 'Inter, system-ui, sans-serif',
  '--apkt-accent': '#16a34a',
  '--apkt-color-mix': '#E8E4DF',
  '--apkt-color-mix-strength': 25,
  '--apkt-border-radius-master': 2,
  '--apkt-z-index': 100000,
} as Record<string, string | number>

function getCurrentTheme(): 'dark' | 'light' {
  const stored = localStorage.getItem('xenarch-theme')
  if (stored === 'light' || stored === 'dark') return stored
  if (window.matchMedia?.('(prefers-color-scheme: light)').matches) return 'light'
  return 'dark'
}

/**
 * Initialise the Reown AppKit singleton with Xenarch theming.
 * No WagmiProvider needed — we use the imperative API only.
 */
export function initAppKit(projectId: string): AppKit {
  if (appKitInstance) return appKitInstance

  const adapter = new WagmiAdapter({
    projectId,
    networks: [base, mainnet],
  })

  const theme = getCurrentTheme()

  appKitInstance = createAppKit({
    adapters: [adapter],
    networks: [base, mainnet],
    metadata,
    projectId,
    features: {
      analytics: false,
      email: false,
      socials: false,
    },
    themeMode: theme,
    themeVariables: theme === 'dark' ? DARK_THEME : LIGHT_THEME,
  })

  // Watch for theme changes (toggled by PHP header button).
  const observer = new MutationObserver(() => {
    if (!appKitInstance) return
    const newTheme = getCurrentTheme()
    appKitInstance.setThemeMode(newTheme)
    appKitInstance.setThemeVariables(newTheme === 'dark' ? DARK_THEME : LIGHT_THEME)
  })

  // Observe the xenarch-admin element for data-theme attribute changes.
  const root = document.getElementById('xenarch-admin')
  if (root) {
    observer.observe(root, { attributes: true, attributeFilter: ['data-theme'] })
  }

  return appKitInstance
}

export function getAppKit(): AppKit | null {
  return appKitInstance
}

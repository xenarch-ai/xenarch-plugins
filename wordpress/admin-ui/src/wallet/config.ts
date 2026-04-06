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
  '--apkt-color-mix-strength': '25',
  '--apkt-border-radius-master': '2',
  '--apkt-z-index': '100000',
} as Record<string, string>

const LIGHT_THEME = {
  '--apkt-font-family': 'Inter, system-ui, sans-serif',
  '--apkt-accent': '#16a34a',
  '--apkt-color-mix': '#E8E4DF',
  '--apkt-color-mix-strength': '25',
  '--apkt-border-radius-master': '2',
  '--apkt-z-index': '100000',
} as Record<string, string>

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

/**
 * Inject custom CSS into AppKit's shadow DOM to align with Xenarch design system.
 * Watches for the <w3m-modal> element to appear, then pierces shadow roots.
 */
function injectAppKitStyles() {
  const XENARCH_STYLE_ID = 'xenarch-appkit-overrides'

  const CSS = `
    /* Force Inter font on all text */
    * { font-family: Inter, system-ui, sans-serif !important; }

    /* Override the blue loading indicator color to our green */
    wui-loading-spinner, .wui-loading-spinner {
      --local-color: var(--wui-color-accent-100, #4ade80) !important;
    }

    /* Buttons: tighter border radius matching our DS */
    wui-button button {
      border-radius: 6px !important;
    }
    wui-button button:hover:enabled {
      border-radius: 6px !important;
    }
    wui-button button:focus-visible:enabled {
      border-radius: 6px !important;
    }

    /* "Get" link: use our accent, remove excess padding */
    wui-icon-link button {
      border-radius: 4px !important;
    }

    /* Tag badges (INSTALLED, QR CODE): tighter radius */
    wui-tag {
      border-radius: 4px !important;
    }

    /* Wallet list items: subtle hover */
    wui-list-wallet:hover {
      background: var(--wui-color-gray-glass-002) !important;
    }

    /* Footer text: match our muted style */
    .wui-text[data-variant="small-400"] {
      opacity: 0.6;
    }
  `

  function inject(shadowRoot: ShadowRoot) {
    if (shadowRoot.getElementById(XENARCH_STYLE_ID)) return
    const style = document.createElement('style')
    style.id = XENARCH_STYLE_ID
    style.textContent = CSS
    shadowRoot.appendChild(style)
  }

  // Recursively inject into all shadow roots within a node
  function walk(node: Element) {
    if (node.shadowRoot) {
      inject(node.shadowRoot)
      node.shadowRoot.querySelectorAll('*').forEach(walk)
    }
  }

  // Watch for w3m-modal to appear and inject styles
  const bodyObserver = new MutationObserver(() => {
    const modal = document.querySelector('w3m-modal')
    if (!modal) return

    // Wait for shadow root to be ready (bail after ~1s)
    let attempts = 0
    const checkShadow = () => {
      if (modal.shadowRoot) {
        inject(modal.shadowRoot)
        walk(modal)

        // Watch for internal changes (view transitions)
        const innerObserver = new MutationObserver(() => walk(modal))
        innerObserver.observe(modal.shadowRoot, { childList: true, subtree: true })
      } else if (++attempts < 60) {
        requestAnimationFrame(checkShadow)
      }
    }
    checkShadow()
  })

  bodyObserver.observe(document.body, { childList: true, subtree: true })
}

export function getAppKit(): AppKit | null {
  return appKitInstance
}

// Auto-run style injection on module load — doesn't depend on initAppKit
injectAppKitStyles()

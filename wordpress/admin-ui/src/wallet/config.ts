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

/**
 * Initialise the Reown AppKit singleton.
 * No WagmiProvider needed — we use the imperative API only.
 */
export function initAppKit(projectId: string): AppKit {
  if (appKitInstance) return appKitInstance

  const adapter = new WagmiAdapter({
    projectId,
    networks: [base, mainnet],
  })

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
  })

  return appKitInstance
}

export function getAppKit(): AppKit | null {
  return appKitInstance
}

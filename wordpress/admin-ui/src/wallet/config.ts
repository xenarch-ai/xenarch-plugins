import { createAppKit } from '@reown/appkit/react'
import { WagmiAdapter } from '@reown/appkit-adapter-wagmi'
import { base } from '@reown/appkit/networks'

const projectId = process.env.REOWN_PROJECT_ID || ''

const metadata = {
  name: 'Xenarch',
  description: 'Xenarch WordPress Plugin - Payout Wallet',
  url: window.location.origin,
  icons: [],
}

const wagmiAdapter = new WagmiAdapter({
  projectId,
  networks: [base],
})

export const appKit = createAppKit({
  adapters: [wagmiAdapter],
  networks: [base],
  metadata,
  projectId,
  features: {
    analytics: false,
    email: false,
    socials: false,
  },
})

export const wagmiConfig = wagmiAdapter.wagmiConfig

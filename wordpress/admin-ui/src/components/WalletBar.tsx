import { useState } from 'react'
import type { Settings } from '../types'

interface Props {
  settings: Settings
}

export function WalletBar({ settings }: Props) {
  const [copied, setCopied] = useState(false)
  const wallet = settings.payout_wallet

  if (!wallet) {
    return (
      <div className="xenarch-wallet-bar">
        <span className="xenarch-dot xenarch-dot--red" />
        No payout wallet configured.{' '}
        Switch to the Settings tab to add one.
      </div>
    )
  }

  const truncated = `${wallet.slice(0, 6)}...${wallet.slice(-4)}`

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(wallet)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      // Clipboard API may fail in some contexts.
    }
  }

  // Wallet type labels:
  //   "External Wallet" = pasted address
  //   "WalletConnect"   = connected via WalletConnect
  //   "Xenarch Wallet"  = platform-generated custodial (v2)
  const walletLabel = settings.wallet_type === 'walletconnect' ? 'WalletConnect' : 'External Wallet'

  return (
    <div className="xenarch-wallet-bar">
      <span className="xenarch-dot xenarch-dot--green" />
      <span className="xenarch-wallet-address">{truncated}</span>
      <button className="xenarch-copy-btn" onClick={handleCopy} title="Copy address">
        {copied ? 'Copied!' : 'Copy'}
      </button>
      <span className="xenarch-wallet-label">{walletLabel}</span>
    </div>
  )
}

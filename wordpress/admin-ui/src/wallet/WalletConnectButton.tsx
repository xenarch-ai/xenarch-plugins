import { getAppKit } from './config'

interface Props {
  connected: boolean
  onDisconnect: () => void
}

export function WalletConnectButton({ connected, onDisconnect }: Props) {
  const handleConnect = () => {
    const appKit = getAppKit()
    if (appKit) appKit.open()
  }

  const handleDisconnect = async () => {
    const appKit = getAppKit()
    if (appKit) {
      try { await appKit.disconnect() } catch {}
    }
    onDisconnect()
  }

  if (connected) {
    return (
      <button className="xenarch-btn xenarch-btn--secondary" onClick={handleDisconnect}>
        Disconnect Wallet
      </button>
    )
  }

  return (
    <button className="xenarch-btn xenarch-btn--secondary" onClick={handleConnect}>
      Connect Wallet
    </button>
  )
}

import { useEffect, useRef } from 'react'
import { getAppKit } from './config'

interface Props {
  connected?: boolean
  onConnect?: (address: string) => void
  onDisconnect?: () => void
}

export function WalletConnectButton({ connected, onConnect, onDisconnect }: Props) {
  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const handleConnect = () => {
    const appKit = getAppKit()
    if (!appKit) return
    appKit.open()

    // Poll for address after modal opens (AppKit has no connect callback).
    if (onConnect && !pollingRef.current) {
      pollingRef.current = setInterval(() => {
        const addr = appKit.getAddress()
        if (addr) {
          clearInterval(pollingRef.current!)
          pollingRef.current = null
          onConnect(addr)
        }
      }, 500)
    }
  }

  // Clean up polling on unmount.
  useEffect(() => {
    return () => {
      if (pollingRef.current) clearInterval(pollingRef.current)
    }
  }, [])

  const handleDisconnect = async () => {
    const appKit = getAppKit()
    if (appKit) {
      try { await appKit.disconnect() } catch {}
    }
    onDisconnect?.()
  }

  if (connected) {
    return (
      <button className="xenarch-wallet-opt" onClick={handleDisconnect}>
        <div className="xenarch-wallet-opt-title">Disconnect wallet</div>
      </button>
    )
  }

  return (
    <button className="xenarch-wallet-opt" onClick={handleConnect}>
      <div className="xenarch-wallet-opt-title">Connect wallet</div>
      <div className="xenarch-wallet-opt-desc">MetaMask, Coinbase, etc.</div>
    </button>
  )
}

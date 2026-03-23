import { useState, useEffect, useCallback } from 'react'

interface Props {
  onConnect: (address: string) => void
}

export function WalletConnectButton({ onConnect }: Props) {
  const [available, setAvailable] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Only enable WalletConnect if a project ID is configured.
    const projectId = process.env.REOWN_PROJECT_ID || ''
    if (!projectId) {
      setLoading(false)
      return
    }

    // Dynamically import to avoid loading heavy WalletConnect SDK when no project ID.
    import('./config')
      .then(() => {
        setAvailable(true)
        setLoading(false)
      })
      .catch(() => {
        setLoading(false)
      })
  }, [])

  const handleConnect = useCallback(async () => {
    if (!available) return

    try {
      const { appKit } = await import('./config')
      await appKit.open()

      // Poll for connected address (AppKit events).
      const checkAddress = setInterval(() => {
        const address = appKit.getAddress()
        if (address) {
          clearInterval(checkAddress)
          onConnect(address)
        }
      }, 500)

      // Stop polling after 60s.
      setTimeout(() => clearInterval(checkAddress), 60_000)
    } catch {
      // User cancelled or error.
    }
  }, [available, onConnect])

  if (loading) {
    return null
  }

  if (!available) {
    return (
      <button className="xenarch-btn xenarch-btn--secondary" disabled>
        Connect Wallet (configure REOWN_PROJECT_ID to enable)
      </button>
    )
  }

  return (
    <button className="xenarch-btn xenarch-btn--secondary" onClick={handleConnect}>
      Connect Wallet
    </button>
  )
}

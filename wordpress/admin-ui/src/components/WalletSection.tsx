import { useState, useEffect, useRef } from 'react'
import type { Settings, SettingsChangeHandler } from '../types'
import * as api from '../api'
import { WalletConnectButton } from '../wallet/WalletConnectButton'
import { getAppKit } from '../wallet/config'

interface Props {
  settings: Settings
  onSettingsChange: SettingsChangeHandler
}

export function WalletSection({ settings, onSettingsChange }: Props) {
  const [wallet, setWallet] = useState(settings.payout_wallet)
  const [saving, setSaving] = useState(false)
  const [notice, setNotice] = useState<{ type: 'success' | 'error'; msg: string } | null>(null)
  const savedAddressRef = useRef(settings.payout_wallet)
  const disconnectingRef = useRef(false)

  // Poll AppKit for connection state every 2s.
  // subscribeAccount is unreliable due to WC Core double-init,
  // but getAddress()/getIsConnectedState() read the final state correctly.
  useEffect(() => {
    const appKit = getAppKit()
    if (!appKit) return

    const poll = setInterval(() => {
      const addr = appKit.getAddress()
      const connected = appKit.getIsConnectedState()

      // Reset disconnecting flag when AppKit reports no connection (disconnect completed)
      if (!connected) {
        disconnectingRef.current = false
      }

      if (connected && addr && addr !== savedAddressRef.current && !disconnectingRef.current) {
        console.log('[Xenarch] Poll detected connection:', addr)
        savedAddressRef.current = addr
        disconnectingRef.current = false

        setWallet(addr)
        onSettingsChange((current: Settings) => ({
          ...current,
          payout_wallet: addr,
          wallet_type: 'walletconnect' as const,
        }))
        setNotice({ type: 'success', msg: 'Wallet connected via WalletConnect.' })
        appKit.close()

        api.updateSettings({
          xenarch_payout_wallet: addr,
          xenarch_wallet_type: 'walletconnect',
        }).catch(() => {
          setNotice({ type: 'error', msg: 'Connected but failed to save. Click Save to retry.' })
        })
      }
    }, 2000)

    return () => clearInterval(poll)
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const handleSave = async () => {
    if (!/^0x[0-9a-fA-F]{40}$/.test(wallet)) {
      setNotice({ type: 'error', msg: 'Invalid wallet address. Must be a valid 0x address.' })
      return
    }

    setSaving(true)
    setNotice(null)
    try {
      const updated = await api.updateSettings({
        xenarch_payout_wallet: wallet,
        xenarch_wallet_type: 'external',
      })
      savedAddressRef.current = wallet
      onSettingsChange(updated)
      setNotice({ type: 'success', msg: 'Wallet address saved.' })
    } catch (err) {
      setNotice({
        type: 'error',
        msg: err instanceof Error ? err.message : 'Failed to save wallet',
      })
    } finally {
      setSaving(false)
    }
  }

  const handleDisconnect = () => {
    disconnectingRef.current = true
    savedAddressRef.current = ''
    setWallet('')
    onSettingsChange((current: Settings) => ({
      ...current,
      payout_wallet: '',
      wallet_type: '' as const,
    }))
    setNotice({ type: 'success', msg: 'Wallet disconnected.' })

    api.updateSettings({ xenarch_payout_wallet: '', xenarch_wallet_type: '' }).catch(() => {})
  }

  const isWalletConnect = settings.wallet_type === 'walletconnect'

  return (
    <div className="xenarch-card">
      <h3>Payout Wallet</h3>
      <p>Your USDC wallet address on Base network. Payments go directly to this address.</p>

      {notice && (
        <div className={`xenarch-notice xenarch-notice--${notice.type}`}>{notice.msg}</div>
      )}

      <div className="xenarch-field">
        <label htmlFor="xen-wallet">Wallet Address</label>
        <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
          <input
            id="xen-wallet"
            type="text"
            className="xenarch-input"
            value={wallet}
            onChange={(e) => setWallet(e.target.value)}
            placeholder="0x..."
            style={{ maxWidth: 420 }}
            readOnly={isWalletConnect}
          />
          {!isWalletConnect && (
            <button
              className="xenarch-btn xenarch-btn--primary"
              onClick={handleSave}
              disabled={saving || !wallet}
            >
              {saving && <span className="xenarch-spinner" />}
              Save
            </button>
          )}
        </div>
      </div>

      <div style={{ marginTop: 12 }}>
        <WalletConnectButton
          connected={isWalletConnect}
          onDisconnect={handleDisconnect}
        />
      </div>

    </div>
  )
}

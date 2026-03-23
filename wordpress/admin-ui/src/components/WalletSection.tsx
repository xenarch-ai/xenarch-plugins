import { useState } from 'react'
import type { Settings } from '../types'
import * as api from '../api'
import { WalletConnectButton } from '../wallet/WalletConnectButton'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
}

export function WalletSection({ settings, onSettingsChange }: Props) {
  const [wallet, setWallet] = useState(settings.payout_wallet)
  const [saving, setSaving] = useState(false)
  const [notice, setNotice] = useState<{ type: 'success' | 'error'; msg: string } | null>(null)

  const handleSave = async () => {
    if (!/^0x[0-9a-fA-F]{40}$/.test(wallet)) {
      setNotice({ type: 'error', msg: 'Invalid wallet address. Must be a valid 0x address.' })
      return
    }

    setSaving(true)
    setNotice(null)
    try {
      const updated = await api.updateSettings({ xenarch_payout_wallet: wallet })
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

  const handleWalletConnect = (address: string) => {
    setWallet(address)
    // Auto-save when connected via WalletConnect.
    api.updateSettings({ xenarch_payout_wallet: address }).then(onSettingsChange).catch(() => {})
  }

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
          />
          <button
            className="xenarch-btn xenarch-btn--primary"
            onClick={handleSave}
            disabled={saving}
          >
            {saving && <span className="xenarch-spinner" />}
            Save
          </button>
        </div>
      </div>

      <div style={{ marginTop: 12 }}>
        <WalletConnectButton onConnect={handleWalletConnect} />
      </div>

      <div style={{ marginTop: 16 }}>
        <span className="xenarch-coming-soon">Coming soon</span>{' '}
        Generate wallet via Xenarch / Withdraw from Xenarch wallet
      </div>
    </div>
  )
}

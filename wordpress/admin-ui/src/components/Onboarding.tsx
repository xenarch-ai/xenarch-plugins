import { useState, useCallback } from 'react'
import type { Settings } from '../types'
import * as api from '../api'
import { WalletConnectButton } from '../wallet/WalletConnectButton'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
}

type Phase = 'cards' | 'manual'

export function Onboarding({ onSettingsChange }: Props) {
  const [phase, setPhase] = useState<Phase>('cards')
  const [manualAddress, setManualAddress] = useState('')
  const [manualNetwork, setManualNetwork] = useState('base')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  const saveWallet = useCallback(async (address: string, type: string, network: string) => {
    setSaving(true)
    setError('')
    try {
      const updated = await api.updateSettings({
        xenarch_payout_wallet: address,
        xenarch_wallet_type: type,
        xenarch_wallet_network: network,
      })
      onSettingsChange(updated)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Something went wrong.')
    }
    setSaving(false)
  }, [onSettingsChange])

  const handleCreateWallet = useCallback(async () => {
    setSaving(true)
    setError('')
    try {
      // TODO: wire to Xenarch platform API for wallet creation
      throw new Error('Wallet creation is not available yet.')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Something went wrong.')
    }
    setSaving(false)
  }, [])

  return (
    <div className="xenarch-onboarding">
      <div className="xenarch-onboarding-title">Where do you want to receive payments?</div>
      <div className="xenarch-onboarding-desc">This is how you'll get paid when AI bots access your content.</div>

      {phase === 'cards' && (
        <div className="xenarch-wallet-options">
          <WalletConnectButton onConnect={(address) => saveWallet(address, 'connected', 'base')} />
          <button
            className="xenarch-wallet-opt"
            onClick={handleCreateWallet}
            disabled={saving}
          >
            <div className="xenarch-wallet-opt-title">Create for me</div>
            <div className="xenarch-wallet-opt-desc">Easiest setup</div>
          </button>
          <button
            className="xenarch-wallet-opt"
            onClick={() => setPhase('manual')}
          >
            <div className="xenarch-wallet-opt-title">Enter manually</div>
            <div className="xenarch-wallet-opt-desc">Paste an address</div>
          </button>
        </div>
      )}

      {phase === 'manual' && (
        <div className="xenarch-wallet-manual">
          <input
            type="text"
            className="xenarch-input xenarch-input--wide"
            value={manualAddress}
            onChange={(e) => setManualAddress(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter' && manualAddress.trim()) saveWallet(manualAddress.trim(), 'manual', manualNetwork) }}
            placeholder="0x..."
            autoFocus
            style={{ fontFamily: "'JetBrains Mono', monospace" }}
          />
          <select
            className="xenarch-wallet-network-select"
            value={manualNetwork}
            onChange={(e) => setManualNetwork(e.target.value)}
          >
            <option value="base">Base</option>
            <option value="solana">Solana</option>
          </select>
          <button
            className="xenarch-wallet-icon-btn xenarch-wallet-icon-btn--confirm"
            onClick={() => { if (manualAddress.trim()) saveWallet(manualAddress.trim(), 'manual', manualNetwork) }}
            disabled={saving}
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><polyline points="3 8.5 6.5 12 13 4"/></svg>
          </button>
          <button
            className="xenarch-wallet-icon-btn xenarch-wallet-icon-btn--cancel"
            onClick={() => { setPhase('cards'); setError('') }}
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><line x1="4" y1="4" x2="12" y2="12"/><line x1="12" y1="4" x2="4" y2="12"/></svg>
          </button>
        </div>
      )}

      {error && (
        <div className="xenarch-onboarding-error">
          {error} {error.includes('not available') ? '' : <span className="xenarch-onboarding-retry" onClick={() => setError('')}>Try again</span>}
        </div>
      )}

      <div className="xenarch-onboarding-footer">One step. That's it. Everything else is configured for you.</div>
    </div>
  )
}

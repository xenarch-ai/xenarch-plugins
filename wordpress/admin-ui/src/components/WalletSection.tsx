import { useState, useCallback, useEffect } from 'react'
import type { Settings } from '../types'
import * as api from '../api'
import { WalletConnectButton } from '../wallet/WalletConnectButton'
import { getAppKit } from '../wallet/config'
import { NetworkSelect } from './NetworkSelect'
import { CreateWalletModal } from './CreateWalletModal'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
  loading?: boolean
}

type Phase = 'setup' | 'manual' | 'configured'

export function WalletSection({ settings, onSettingsChange, loading }: Props) {
  const hasWallet = !!settings.payout_wallet
  const [phase, setPhase] = useState<Phase>(hasWallet ? 'configured' : 'setup')
  const [manualAddress, setManualAddress] = useState('')
  const [manualNetwork, setManualNetwork] = useState('base')
  const [saving, setSaving] = useState(false)
  const [balance, setBalance] = useState<string | null>(null)

  const walletType = settings.wallet_type || 'manual'
  const isXenarchWallet = walletType === 'xenarch' || walletType === 'coinbase'

  // Fetch balance for Xenarch wallets to know if change should be blocked.
  useEffect(() => {
    if (hasWallet && isXenarchWallet) {
      api.fetchBalance().then(data => setBalance(data.balance_usd)).catch(() => setBalance(null))
    }
  }, [hasWallet, isXenarchWallet])

  // Keep phase in sync if wallet changes externally.
  useEffect(() => {
    setPhase(settings.payout_wallet ? 'configured' : 'setup')
  }, [settings.payout_wallet])

  const saveWallet = useCallback(async (address: string, type: string, network: string) => {
    setSaving(true)
    try {
      const updated = await api.updateSettings({
        xenarch_payout_wallet: address,
        xenarch_wallet_type: type,
        xenarch_wallet_network: network,
      })
      onSettingsChange(updated)
      setPhase('configured')
    } catch {}
    setSaving(false)
  }, [onSettingsChange])

  const handleWalletConnect = useCallback(async (address: string) => {
    // Disconnect existing connection before saving the new one.
    const appKit = getAppKit()
    if (appKit?.getIsConnectedState?.()) {
      try { await appKit.disconnect() } catch {}
    }
    saveWallet(address, 'connected', 'base')
  }, [saveWallet])

  const [error] = useState('')

  const [showCreateModal, setShowCreateModal] = useState(false)

  const handleCreateWallet = useCallback(() => {
    setShowCreateModal(true)
  }, [])

  const handleWalletCreated = useCallback((address: string) => {
    setShowCreateModal(false)
    saveWallet(address, 'coinbase', 'base')
  }, [saveWallet])

  const handleManualSave = useCallback(() => {
    if (!manualAddress.trim()) return
    saveWallet(manualAddress.trim(), 'manual', manualNetwork)
  }, [manualAddress, manualNetwork, saveWallet])


  const handleChange = () => {
    // Xenarch wallet with balance > 0: block change.
    if (isXenarchWallet && balance && parseFloat(balance) > 0) return
    setPhase('setup')
  }

  const truncateAddress = (addr: string) => {
    if (addr.length <= 12) return addr
    return `${addr.slice(0, 6)}...${addr.slice(-4)}`
  }

  const badgeText = walletType === 'xenarch' || walletType === 'coinbase' ? 'xenarch wallet' : walletType === 'connected' ? 'connected via WalletConnect' : 'your wallet'
  const badgeClass = walletType === 'xenarch' || walletType === 'coinbase' ? 'xenarch-wallet-badge--xenarch' : 'xenarch-wallet-badge--external'
  const noteText = isXenarchWallet
    ? 'Your wallet. Cash out to your bank from the Earnings tab.'
    : 'Settled on-chain directly to your wallet. No funds held by Xenarch.'

  const changeBlocked = isXenarchWallet && balance !== null && parseFloat(balance) > 0

  // Loading skeleton
  if (loading) {
    return (
      <div className="xenarch-section">
        <div className="xenarch-skeleton" style={{ width: 60, height: 16, marginBottom: 6 }} />
        <div className="xenarch-skeleton" style={{ width: 260, height: 12, marginBottom: 16 }} />
        <div style={{ display: 'flex', gap: 8 }}>
          <div className="xenarch-skeleton" style={{ flex: 1, height: 52, borderRadius: 8 }} />
          <div className="xenarch-skeleton" style={{ flex: 1, height: 52, borderRadius: 8 }} />
          <div className="xenarch-skeleton" style={{ flex: 1, height: 52, borderRadius: 8 }} />
        </div>
      </div>
    )
  }

  return (
    <div className="xenarch-section">
      <div className="xenarch-section-title">Wallet</div>

      {/* Phase 1: Setup -- 3 option cards */}
      {phase === 'setup' && (() => {
        const isChanging = hasWallet && phase === 'setup'
        return (
          <>
            <div className="xenarch-section-desc">Where do you want to receive payments?</div>
            {isChanging && (
              <div className="xenarch-wallet-disconnect-notice">
                Connecting a new wallet will replace your current one.
              </div>
            )}
            <div className="xenarch-wallet-options">
              {isChanging && walletType === 'connected' ? (
                <button className="xenarch-wallet-opt" onClick={() => setPhase('configured')}>
                  <div className="xenarch-wallet-opt-title">Current</div>
                  <div className="xenarch-wallet-opt-desc">{truncateAddress(settings.payout_wallet)}</div>
                </button>
              ) : (
                <WalletConnectButton onConnect={(address) => handleWalletConnect(address)} />
              )}
              {isChanging && isXenarchWallet ? (
                <button className="xenarch-wallet-opt" onClick={() => setPhase('configured')}>
                  <div className="xenarch-wallet-opt-title">Current</div>
                  <div className="xenarch-wallet-opt-desc">{truncateAddress(settings.payout_wallet)}</div>
                </button>
              ) : (
                <button className="xenarch-wallet-opt" onClick={handleCreateWallet} disabled={saving}>
                  <div className="xenarch-wallet-opt-title">Create for me</div>
                  <div className="xenarch-wallet-opt-desc">Cash out to your bank</div>
                </button>
              )}
              {isChanging && walletType === 'manual' ? (
                <button className="xenarch-wallet-opt" onClick={() => setPhase('configured')}>
                  <div className="xenarch-wallet-opt-title">Current</div>
                  <div className="xenarch-wallet-opt-desc">{truncateAddress(settings.payout_wallet)}</div>
                </button>
              ) : (
                <button className="xenarch-wallet-opt" onClick={() => setPhase('manual')}>
                  <div className="xenarch-wallet-opt-title">Enter manually</div>
                  <div className="xenarch-wallet-opt-desc">Base or Solana address</div>
                </button>
              )}
            </div>
            {error && <div className="xenarch-onboarding-error">Something went wrong. Try again.</div>}
          </>
        )
      })()}

      {/* Phase 1b: Manual entry */}
      {phase === 'manual' && (
        <div className="xenarch-wallet-manual">
          <input
            type="text"
            className="xenarch-input xenarch-input--wide"
            value={manualAddress}
            onChange={(e) => setManualAddress(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') handleManualSave() }}
            placeholder="0x..."
            autoFocus
          />
          <NetworkSelect value={manualNetwork} onChange={setManualNetwork} />
          <button
            className="xenarch-wallet-icon-btn xenarch-wallet-icon-btn--confirm"
            onClick={handleManualSave}
            title="Save"
            disabled={saving}
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><polyline points="3 8.5 6.5 12 13 4"/></svg>
          </button>
          <button
            className="xenarch-wallet-icon-btn xenarch-wallet-icon-btn--cancel"
            onClick={() => setPhase(hasWallet ? 'configured' : 'setup')}
            title="Cancel"
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><line x1="4" y1="4" x2="12" y2="12"/><line x1="12" y1="4" x2="4" y2="12"/></svg>
          </button>
        </div>
      )}

      {/* Phase 2: Configured */}
      {phase === 'configured' && (
        <div className="xenarch-wallet-configured">
          <div>
            <div>
              <span className="xenarch-wallet-addr">{truncateAddress(settings.payout_wallet)}</span>
              <span className={`xenarch-wallet-badge ${badgeClass}`}>{badgeText}</span>
            </div>
            <div className="xenarch-wallet-note">{noteText}</div>
          </div>
          <div style={{ textAlign: 'right' }}>
            <button
              className={`xenarch-wallet-change${changeBlocked ? ' xenarch-wallet-change--disabled' : ''}`}
              onClick={handleChange}
              disabled={changeBlocked}
            >
              Change
            </button>
            {changeBlocked && (
              <div className="xenarch-wallet-change-hint">Cash out your balance first — go to the Earnings tab</div>
            )}
          </div>
        </div>
      )}

      {showCreateModal && (
        <CreateWalletModal
          onWalletCreated={handleWalletCreated}
          onClose={() => setShowCreateModal(false)}
        />
      )}
    </div>
  )
}

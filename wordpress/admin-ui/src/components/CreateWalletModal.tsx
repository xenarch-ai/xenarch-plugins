/**
 * Coinbase wallet creation modal.
 *
 * Renders as an inline overlay (like BotDetailModal), not a popup.
 * Uses a hidden iframe to api.xenarch.dev for the Coinbase CDP SDK.
 * Same pattern as Reown AppKit's Connect Wallet modal.
 */
import { useState, useEffect, useCallback, useRef } from 'react'
import { createWalletBridge, type WalletBridge } from '../wallet/createWalletBridge'

interface Props {
  onWalletCreated: (address: string) => void
  onClose: () => void
}

type Phase = 'initializing' | 'email' | 'otp' | 'success'

export function CreateWalletModal({ onWalletCreated, onClose }: Props) {
  const [phase, setPhase] = useState<Phase>('initializing')
  const [email, setEmail] = useState('')
  const [otp, setOtp] = useState('')
  const [flowId, setFlowId] = useState('')
  const [address, setAddress] = useState('')
  const [isNewUser, setIsNewUser] = useState(true)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const bridgeRef = useRef<WalletBridge | null>(null)

  // Lock body scroll (same as BotDetailModal)
  useEffect(() => {
    document.body.style.overflow = 'hidden'
    return () => { document.body.style.overflow = '' }
  }, [])

  // Initialize bridge on mount
  useEffect(() => {
    const siteToken = window.xenarchAdmin?.settings?.site_token || ''
    if (!siteToken) {
      setError('Site not configured. Please complete setup first.')
      return
    }

    createWalletBridge(siteToken)
      .then((bridge) => {
        bridgeRef.current = bridge
        setPhase('email')
      })
      .catch((err) => {
        setError(err.message || 'Failed to connect to wallet service.')
      })

    return () => {
      bridgeRef.current?.destroy()
    }
  }, [])

  const handleSendOtp = useCallback(async () => {
    if (!email.trim() || !bridgeRef.current) return
    setLoading(true)
    setError('')
    try {
      const result = await bridgeRef.current.sendOtp(email.trim())
      setFlowId(result.flowId)
      setPhase('otp')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to send code.')
    }
    setLoading(false)
  }, [email])

  const handleVerifyOtp = useCallback(async () => {
    if (!otp.trim() || !flowId || !bridgeRef.current) return
    setLoading(true)
    setError('')
    try {
      const result = await bridgeRef.current.verifyOtp(flowId, otp.trim())
      setAddress(result.address)
      setIsNewUser(result.isNewUser)
      setPhase('success')
      // Notify parent after brief delay
      setTimeout(() => {
        onWalletCreated(result.address)
      }, 1500)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verification failed.')
    }
    setLoading(false)
  }, [otp, flowId, onWalletCreated])

  const handleOverlayClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) onClose()
  }

  return (
    <div className="xenarch-modal-overlay xenarch-modal-overlay--wallet" onClick={handleOverlayClick}>
      <div className="xenarch-modal xenarch-modal--wallet">
        <div className="xenarch-modal-top">
          <div className="xenarch-modal-header">
            <div className="xenarch-modal-title">Create wallet</div>
            <button className="xenarch-modal-close" onClick={onClose}>&times;</button>
          </div>
        </div>

        <div className="xenarch-wallet-modal-body">
          {phase === 'initializing' && (
            <div className="xenarch-wallet-modal-center">
              {error ? (
                <>
                  <div className="xenarch-wallet-modal-error">{error}</div>
                  <button className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--secondary" onClick={onClose}>
                    Close
                  </button>
                </>
              ) : (
                <div className="xenarch-wallet-modal-loading">
                  <div className="xenarch-wallet-modal-spinner" />
                  <div className="xenarch-wallet-modal-info">Setting up secure connection...</div>
                </div>
              )}
            </div>
          )}

          {phase === 'email' && (
            <>
              <label className="xenarch-wallet-modal-label">Email address</label>
              <input
                type="email"
                className="xenarch-wallet-modal-input"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') handleSendOtp() }}
                placeholder="you@example.com"
                autoFocus
                disabled={loading}
              />
              <button
                className="xenarch-wallet-modal-btn"
                onClick={handleSendOtp}
                disabled={loading || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())}
              >
                {loading ? 'Sending...' : 'Send verification code'}
              </button>
              {error && <div className="xenarch-wallet-modal-error">{error}</div>}
              <div className="xenarch-wallet-modal-info">
                A crypto wallet will be created for you via Coinbase. No crypto knowledge needed.
              </div>
            </>
          )}

          {phase === 'otp' && (
            <>
              <label className="xenarch-wallet-modal-label">Verification code</label>
              <div className="xenarch-wallet-modal-info" style={{ marginBottom: 8, marginTop: 0 }}>
                Enter the 6-digit code sent to {email}
              </div>
              <input
                type="text"
                className="xenarch-wallet-modal-input xenarch-wallet-modal-otp"
                value={otp}
                onChange={(e) => setOtp(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') handleVerifyOtp() }}
                placeholder="000000"
                maxLength={6}
                autoFocus
                disabled={loading}
              />
              <button
                className="xenarch-wallet-modal-btn"
                onClick={handleVerifyOtp}
                disabled={loading || otp.trim().length < 6}
              >
                {loading ? 'Verifying...' : 'Verify'}
              </button>
              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--secondary"
                onClick={() => { setPhase('email'); setOtp(''); setError('') }}
              >
                Back
              </button>
              {error && <div className="xenarch-wallet-modal-error">{error}</div>}
            </>
          )}

          {phase === 'success' && (
            <div className="xenarch-wallet-modal-center">
              <div className="xenarch-wallet-modal-check">&#10003;</div>
              <div className="xenarch-wallet-modal-success-title">{isNewUser ? 'Wallet created' : 'Wallet connected'}</div>
              <div className="xenarch-wallet-modal-address">{address}</div>
              <div className="xenarch-wallet-modal-info">Saving...</div>
            </div>
          )}
        </div>

        <div className="xenarch-wallet-modal-footer">Secured by Coinbase</div>
      </div>
    </div>
  )
}

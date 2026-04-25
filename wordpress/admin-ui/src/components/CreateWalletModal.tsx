/**
 * Coinbase wallet creation modal.
 *
 * Renders as an inline overlay (like BotDetailModal), not a popup.
 * Uses a hidden iframe to xenarch.dev for the Coinbase CDP SDK.
 * Same pattern as Reown AppKit's Connect Wallet modal.
 *
 * Supports: email + OTP, Google OAuth, Apple OAuth.
 */
import { useState, useEffect, useCallback, useRef } from 'react'
import { createWalletBridge, type WalletBridge } from '../wallet/createWalletBridge'

interface Props {
  onWalletCreated: (address: string) => void
  onClose: () => void
}

type Phase = 'initializing' | 'choose' | 'email' | 'otp' | 'oauth-waiting' | 'success'

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

        // Listen for OAuth wallet creation (async, popup-based)
        bridge.onWalletCreated((data) => {
          setAddress(data.address)
          setIsNewUser(data.isNewUser)
          setPhase('success')
          setTimeout(() => {
            onWalletCreated(data.address)
          }, 1500)
        })

        bridge.onOAuthError((err) => {
          setError(err)
          setPhase('choose')
          setLoading(false)
        })

        setPhase('choose')
      })
      .catch((err) => {
        setError(err.message || 'Failed to connect to wallet service.')
      })

    return () => {
      bridgeRef.current?.destroy()
    }
  }, [onWalletCreated])

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

  const handleOAuth = useCallback(async (provider: 'google' | 'apple') => {
    if (!bridgeRef.current) return
    setError('')
    setLoading(true)
    setPhase('oauth-waiting')
    try {
      await bridgeRef.current.startOAuth(provider)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to open sign-in window.')
      setPhase('choose')
      setLoading(false)
    }
  }, [])

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

          {phase === 'choose' && (
            <>
              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--oauth"
                onClick={() => handleOAuth('google')}
                disabled={loading}
              >
                <svg width="18" height="18" viewBox="0 0 18 18" style={{ marginRight: 8, verticalAlign: 'middle' }}>
                  <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z"/>
                  <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
                  <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.997 8.997 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>
                  <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
                </svg>
                Continue with Google
              </button>
              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--oauth"
                onClick={() => handleOAuth('apple')}
                disabled={loading}
              >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor" style={{ marginRight: 8, verticalAlign: 'middle' }}>
                  <path d="M14.94 9.88c-.02-2.1 1.72-3.12 1.8-3.17-1-1.44-2.5-1.64-3.04-1.66-1.28-.14-2.52.76-3.18.76-.66 0-1.66-.74-2.74-.72-1.4.02-2.7.82-3.42 2.08-1.48 2.56-.38 6.34 1.04 8.42.72 1.02 1.56 2.16 2.66 2.12 1.08-.04 1.48-.68 2.78-.68 1.3 0 1.66.68 2.78.66 1.14-.02 1.86-1.02 2.54-2.06.82-1.18 1.14-2.32 1.16-2.38-.02-.02-2.22-.86-2.24-3.38h-.1zM12.82 3.3c.58-.72.98-1.7.86-2.7-.84.04-1.88.58-2.48 1.28-.54.62-1.02 1.64-.9 2.6.94.08 1.92-.48 2.52-1.18z"/>
                </svg>
                Continue with Apple
              </button>

              <div className="xenarch-wallet-modal-divider">
                <span>or</span>
              </div>

              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--secondary"
                onClick={() => setPhase('email')}
              >
                Continue with email
              </button>

              {error && <div className="xenarch-wallet-modal-error">{error}</div>}
              <div className="xenarch-wallet-modal-info">
                A crypto wallet will be created for you via Coinbase. No crypto knowledge needed.
              </div>
            </>
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
              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--secondary"
                onClick={() => { setPhase('choose'); setError('') }}
              >
                Back
              </button>
              {error && <div className="xenarch-wallet-modal-error">{error}</div>}
              <div className="xenarch-wallet-modal-info">
                A verification code will be sent to your email.
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

          {phase === 'oauth-waiting' && (
            <div className="xenarch-wallet-modal-center">
              <div className="xenarch-wallet-modal-loading">
                <div className="xenarch-wallet-modal-spinner" />
                <div className="xenarch-wallet-modal-info">Complete sign-in in the popup window...</div>
              </div>
              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--secondary"
                onClick={() => { setPhase('choose'); setLoading(false); setError('') }}
                style={{ marginTop: 16 }}
              >
                Cancel
              </button>
            </div>
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

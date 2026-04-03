/**
 * Cash out modal — converts USDC to fiat via Coinbase Offramp.
 *
 * Same visual pattern as CreateWalletModal: inline overlay, body scroll lock,
 * same CSS classes for the modal shell. Opens Coinbase Pay in a new tab.
 */
import { useState, useEffect, useCallback, useRef } from 'react'
import type { SellOptions, SellQuote } from '../types'
import * as api from '../api'

interface Props {
  balance: string
  onComplete: () => void
  onClose: () => void
}

type Phase = 'loading' | 'form' | 'quote' | 'redirected' | 'error'

function detectCountry(): string {
  const stored = localStorage.getItem('xenarch-cashout-country')
  if (stored) return stored
  const lang = navigator.language || 'en-US'
  const parts = lang.split('-')
  return parts.length > 1 && parts[1] ? parts[1].toUpperCase() : 'US'
}

export function CashOutModal({ balance, onComplete, onClose }: Props) {
  const [phase, setPhase] = useState<Phase>('loading')
  const [country, setCountry] = useState(detectCountry)
  const [amount, setAmount] = useState(balance)
  const [options, setOptions] = useState<SellOptions | null>(null)
  const [quote, setQuote] = useState<SellQuote | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const initialBalance = useRef(balance)

  // Lock body scroll
  useEffect(() => {
    document.body.style.overflow = 'hidden'
    return () => { document.body.style.overflow = '' }
  }, [])

  // Load sell options on mount
  useEffect(() => {
    api.fetchSellOptions(country)
      .then((data) => {
        if (data.error || data.supported === false) {
          setError(`Cash out isn't available in your country yet. You can log in to coinbase.com with the same email to manage your wallet directly.`)
          setPhase('error')
        } else {
          setOptions(data)
          setPhase('form')
        }
      })
      .catch(() => {
        setError('Failed to check availability. Please try again.')
        setPhase('error')
      })
  }, [country])

  // Listen for tab visibility changes in redirected phase
  useEffect(() => {
    if (phase !== 'redirected') return

    const handleVisibility = () => {
      if (document.visibilityState === 'visible') {
        // Poll balance to see if it decreased
        api.fetchBalance().then((data) => {
          if (parseFloat(data.balance_usd) < parseFloat(initialBalance.current)) {
            onComplete()
          }
        }).catch(() => {})
      }
    }

    document.addEventListener('visibilitychange', handleVisibility)
    return () => document.removeEventListener('visibilitychange', handleVisibility)
  }, [phase, onComplete])

  const handleCountryChange = useCallback((newCountry: string) => {
    const c = newCountry.toUpperCase().slice(0, 2)
    setCountry(c)
    localStorage.setItem('xenarch-cashout-country', c)
    setPhase('loading')
  }, [])

  const handleGetQuote = useCallback(async () => {
    if (!amount || parseFloat(amount) <= 0) return
    setLoading(true)
    setError('')
    try {
      const q = await api.createSellQuote(amount, country)
      setQuote(q)
      setPhase('quote')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to get quote.')
    }
    setLoading(false)
  }, [amount, country])

  const handleContinue = useCallback(() => {
    if (!quote?.offramp_url) return
    window.open(quote.offramp_url, '_blank')
    setPhase('redirected')
  }, [quote])

  const handleDone = useCallback(() => {
    onComplete()
  }, [onComplete])

  const handleOverlayClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) onClose()
  }

  const minAmount = options?.payment_methods?.[0]?.min_amount || '1.00'
  const maxAmount = options?.payment_methods?.[0]?.max_amount || '25000.00'
  const paymentMethod = options?.payment_methods?.[0]?.name || 'Bank account'

  return (
    <div className="xenarch-modal-overlay xenarch-modal-overlay--wallet" onClick={handleOverlayClick}>
      <div className="xenarch-modal xenarch-modal--wallet">
        <div className="xenarch-modal-top">
          <div className="xenarch-modal-header">
            <div className="xenarch-modal-title">Cash out</div>
            <button className="xenarch-modal-close" onClick={onClose}>&times;</button>
          </div>
        </div>

        <div className="xenarch-wallet-modal-body">
          {phase === 'loading' && (
            <div className="xenarch-wallet-modal-center">
              <div className="xenarch-wallet-modal-loading">
                <div className="xenarch-wallet-modal-spinner" />
                <div className="xenarch-wallet-modal-info">Checking availability...</div>
              </div>
            </div>
          )}

          {phase === 'form' && (
            <>
              <div className="xenarch-cashout-balance">
                Balance: <strong>${balance} USDC</strong>
              </div>

              <label className="xenarch-wallet-modal-label">Amount (USDC)</label>
              <div className="xenarch-cashout-amount-row">
                <input
                  type="text"
                  className="xenarch-wallet-modal-input"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') handleGetQuote() }}
                  placeholder="0.00"
                  autoFocus
                  disabled={loading}
                />
                <button
                  className="xenarch-cashout-btn-max"
                  onClick={() => setAmount(balance)}
                  disabled={loading}
                >
                  MAX
                </button>
              </div>

              <div className="xenarch-cashout-meta">
                <div className="xenarch-cashout-meta-row">
                  <span>Country</span>
                  <input
                    type="text"
                    className="xenarch-cashout-country-input"
                    value={country}
                    onChange={(e) => handleCountryChange(e.target.value)}
                    maxLength={2}
                  />
                </div>
                <div className="xenarch-cashout-meta-row">
                  <span>Method</span>
                  <span>{paymentMethod}</span>
                </div>
                <div className="xenarch-cashout-meta-row xenarch-wallet-modal-info">
                  Min ${minAmount} &middot; Max ${maxAmount} &middot; Zero fees for USDC
                </div>
              </div>

              <button
                className="xenarch-wallet-modal-btn"
                onClick={handleGetQuote}
                disabled={loading || !amount || parseFloat(amount) <= 0}
              >
                {loading ? 'Getting quote...' : 'Get quote'}
              </button>
              {error && <div className="xenarch-wallet-modal-error">{error}</div>}
            </>
          )}

          {phase === 'quote' && quote && (
            <>
              <div className="xenarch-cashout-quote-card">
                <div className="xenarch-cashout-quote-row">
                  <span>You sell</span>
                  <strong>{quote.sell_amount} USDC</strong>
                </div>
                <div className="xenarch-cashout-quote-row">
                  <span>You receive</span>
                  <strong>${quote.buy_amount} USD</strong>
                </div>
                <div className="xenarch-cashout-quote-row">
                  <span>Fees</span>
                  <span>${quote.fee_amount}</span>
                </div>
              </div>

              <div className="xenarch-wallet-modal-info">
                You'll be redirected to Coinbase to complete the sale. Funds arrive in 1-3 business days.
              </div>

              <button className="xenarch-wallet-modal-btn" onClick={handleContinue}>
                Continue to Coinbase
              </button>
              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--secondary"
                onClick={() => { setPhase('form'); setQuote(null) }}
              >
                Back
              </button>
            </>
          )}

          {phase === 'redirected' && (
            <div className="xenarch-wallet-modal-center">
              <div className="xenarch-cashout-waiting-dot" />
              <div className="xenarch-wallet-modal-success-title">Complete your cash out on Coinbase</div>
              <div className="xenarch-wallet-modal-info">
                This page will update when done. If you've already finished:
              </div>
              <button className="xenarch-wallet-modal-btn" onClick={handleDone}>
                I'm done
              </button>
            </div>
          )}

          {phase === 'error' && (
            <div className="xenarch-wallet-modal-center">
              <div className="xenarch-wallet-modal-error">{error}</div>
              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--secondary"
                onClick={() => { setError(''); setPhase('loading') }}
              >
                Try again
              </button>
              <button
                className="xenarch-wallet-modal-btn xenarch-wallet-modal-btn--secondary"
                onClick={onClose}
              >
                Close
              </button>
            </div>
          )}
        </div>

        <div className="xenarch-wallet-modal-footer">Secured by Coinbase</div>
      </div>
    </div>
  )
}

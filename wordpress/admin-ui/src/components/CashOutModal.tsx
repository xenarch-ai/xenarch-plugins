/**
 * Cash out modal — converts USDC to fiat via Coinbase Offramp.
 *
 * Same visual pattern as CreateWalletModal: inline overlay, body scroll lock,
 * same CSS classes for the modal shell. Opens Coinbase Pay in a new tab.
 */
import { useState, useEffect, useCallback, useRef } from 'react'
import type { SellOptions, SellQuote, SellConfigCountry } from '../types'
import * as api from '../api'

interface Props {
  balance: string
  onComplete: () => void
  onClose: () => void
}

type Phase = 'loading' | 'form' | 'quote' | 'redirected' | 'error'

const COUNTRY_LS_KEY = 'xenarch-cashout-country'

/** Map of ISO 3166-1 alpha-2 codes to country names. */
const COUNTRY_NAMES: Record<string, string> = {
  AD: 'Andorra', AE: 'United Arab Emirates', AL: 'Albania', AR: 'Argentina',
  AT: 'Austria', AU: 'Australia', BE: 'Belgium', BG: 'Bulgaria',
  BH: 'Bahrain', BR: 'Brazil', CA: 'Canada', CH: 'Switzerland',
  CL: 'Chile', CO: 'Colombia', CR: 'Costa Rica', CY: 'Cyprus',
  CZ: 'Czechia', DE: 'Germany', DK: 'Denmark', DO: 'Dominican Republic',
  EC: 'Ecuador', EE: 'Estonia', ES: 'Spain', FI: 'Finland',
  FR: 'France', GB: 'United Kingdom', GR: 'Greece', GT: 'Guatemala',
  HK: 'Hong Kong', HR: 'Croatia', HU: 'Hungary', ID: 'Indonesia',
  IE: 'Ireland', IL: 'Israel', IN: 'India', IS: 'Iceland',
  IT: 'Italy', JM: 'Jamaica', JO: 'Jordan', JP: 'Japan',
  KE: 'Kenya', KR: 'South Korea', KW: 'Kuwait', LI: 'Liechtenstein',
  LT: 'Lithuania', LU: 'Luxembourg', LV: 'Latvia', MA: 'Morocco',
  MC: 'Monaco', MT: 'Malta', MX: 'Mexico', MY: 'Malaysia',
  NG: 'Nigeria', NL: 'Netherlands', NO: 'Norway', NZ: 'New Zealand',
  OM: 'Oman', PA: 'Panama', PE: 'Peru', PH: 'Philippines',
  PL: 'Poland', PT: 'Portugal', PY: 'Paraguay', QA: 'Qatar',
  RO: 'Romania', SA: 'Saudi Arabia', SE: 'Sweden', SG: 'Singapore',
  SI: 'Slovenia', SK: 'Slovakia', SV: 'El Salvador', TH: 'Thailand',
  TN: 'Tunisia', TR: 'Turkey', TW: 'Taiwan', UA: 'Ukraine',
  US: 'United States', UY: 'Uruguay', VN: 'Vietnam', ZA: 'South Africa',
}

function getCountryName(code: string): string {
  return COUNTRY_NAMES[code] || code
}

export function CashOutModal({ balance, onComplete, onClose }: Props) {
  const [phase, setPhase] = useState<Phase>('loading')
  const [countries, setCountries] = useState<SellConfigCountry[]>([])
  const [country, setCountry] = useState('')
  const [amount, setAmount] = useState(balance)
  const [options, setOptions] = useState<SellOptions | null>(null)
  const [quote, setQuote] = useState<SellQuote | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [optionsLoading, setOptionsLoading] = useState(false)
  const initialBalance = useRef(balance)

  // Lock body scroll
  useEffect(() => {
    document.body.style.overflow = 'hidden'
    return () => { document.body.style.overflow = '' }
  }, [])

  // Fetch supported countries on mount
  useEffect(() => {
    api.fetchSellConfig()
      .then((data) => {
        if (data.error || !data.countries?.length) {
          setError('Cash out is temporarily unavailable. Please try again later.')
          setPhase('error')
          return
        }
        const sorted = [...data.countries].sort((a, b) =>
          getCountryName(a.id).localeCompare(getCountryName(b.id))
        )
        setCountries(sorted)

        // Restore previous selection if valid
        const stored = localStorage.getItem(COUNTRY_LS_KEY)
        if (stored && sorted.some((c) => c.id === stored)) {
          setCountry(stored)
          // Fetch sell options for the stored country
          loadSellOptions(stored)
        } else {
          if (stored) localStorage.removeItem(COUNTRY_LS_KEY)
          setPhase('form')
        }
      })
      .catch(() => {
        setError('Failed to load supported countries. Please try again.')
        setPhase('error')
      })
  }, [])

  const loadSellOptions = useCallback((countryCode: string) => {
    setOptionsLoading(true)
    setError('')
    api.fetchSellOptions(countryCode)
      .then((data) => {
        if (data.error || data.supported === false) {
          setError(`Cash out isn't available in your country yet. You can log in to coinbase.com with the same email to manage your wallet directly.`)
          setOptions(null)
        } else {
          setOptions(data)
          localStorage.setItem(COUNTRY_LS_KEY, countryCode)
        }
        setOptionsLoading(false)
        setPhase('form')
      })
      .catch(() => {
        setError('Failed to check availability. Please try again.')
        setOptions(null)
        setOptionsLoading(false)
        setPhase('form')
      })
  }, [])

  const handleCountrySelect = useCallback((newCountry: string) => {
    setCountry(newCountry)
    setOptions(null)
    setError('')
    if (newCountry) {
      loadSellOptions(newCountry)
    }
  }, [loadSellOptions])

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

  const handleGetQuote = useCallback(async () => {
    if (!amount || parseFloat(amount) <= 0 || !country) return
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
  const canGetQuote = !!country && !!options && !error && !!amount && parseFloat(amount) > 0

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
                <div className="xenarch-wallet-modal-info">Loading...</div>
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
                  onKeyDown={(e) => { if (e.key === 'Enter' && canGetQuote) handleGetQuote() }}
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
                  <select
                    className="xenarch-cashout-country-select"
                    value={country}
                    onChange={(e) => handleCountrySelect(e.target.value)}
                    disabled={optionsLoading}
                  >
                    <option value="" disabled>Select country</option>
                    {countries.map((c) => (
                      <option key={c.id} value={c.id}>
                        {getCountryName(c.id)}
                      </option>
                    ))}
                  </select>
                </div>
                {options && (
                  <>
                    <div className="xenarch-cashout-meta-row">
                      <span>Method</span>
                      <span>{paymentMethod}</span>
                    </div>
                    <div className="xenarch-cashout-meta-row xenarch-wallet-modal-info">
                      Min ${minAmount} &middot; Max ${maxAmount} &middot; Zero fees for USDC
                    </div>
                  </>
                )}
              </div>

              {optionsLoading && (
                <div className="xenarch-wallet-modal-info" style={{ textAlign: 'center' }}>
                  Checking availability...
                </div>
              )}

              <button
                className="xenarch-wallet-modal-btn"
                onClick={handleGetQuote}
                disabled={!canGetQuote || loading || optionsLoading}
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
                onClick={() => { setError(''); setPhase('loading'); location.reload() }}
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

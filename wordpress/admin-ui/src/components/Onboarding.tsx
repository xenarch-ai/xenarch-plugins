import { useState, useCallback } from 'react'

const DASHBOARD_CLAIM_BASE = 'https://dash.xenarch.dev/sites/claim'

interface Props {
  domain: string
  pluginAdminUrl: string
}

/**
 * First-run screen. The plugin's only setup question is "connect to your
 * Xenarch account" — there is no wallet question, no email/password,
 * no token paste. The merchant clicks Connect, signs SIWE on the
 * dashboard (where the wallet UI already lives), confirms claiming
 * this domain, and is bounced back here with a one-time claim_token
 * that App.tsx exchanges for the persistent site_token.
 */
export function Onboarding({ domain, pluginAdminUrl }: Props) {
  const [redirecting, setRedirecting] = useState(false)

  const connect = useCallback(() => {
    setRedirecting(true)
    const callback = pluginAdminUrl // current page; dashboard appends ?claim_token=…
    const url = new URL(DASHBOARD_CLAIM_BASE)
    url.searchParams.set('domain', domain)
    url.searchParams.set('integration', 'wp')
    url.searchParams.set('callback', callback)
    window.location.href = url.toString()
  }, [domain, pluginAdminUrl])

  return (
    <div className="xenarch-onboarding">
      <div className="xenarch-onboarding-title">Connect this site to your Xenarch account</div>
      <div className="xenarch-onboarding-desc">
        Pairing is one-click. You'll sign in to your Xenarch account on{' '}
        <code style={{ fontFamily: "'JetBrains Mono', monospace" }}>dash.xenarch.dev</code>,
        confirm <b>{domain || 'this site'}</b>, and come right back. Payout
        wallet, pricing rules, and gating are all managed in the dashboard —
        the plugin just enforces what you set there.
      </div>

      <div style={{ marginTop: '1.5rem' }}>
        <button
          className="xenarch-btn xenarch-btn--primary"
          onClick={connect}
          disabled={redirecting}
        >
          {redirecting ? 'Redirecting…' : 'Connect with Xenarch'}
        </button>
      </div>

      <div className="xenarch-onboarding-footer">
        No Xenarch account yet?{' '}
        <a
          href={`${DASHBOARD_CLAIM_BASE.replace('/sites/claim', '/auth')}`}
          target="_blank"
          rel="noopener noreferrer"
        >
          Sign up with your wallet →
        </a>
      </div>
    </div>
  )
}

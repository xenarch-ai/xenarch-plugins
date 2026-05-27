import { useState, useCallback } from 'react'

const DASHBOARD_CLAIM_BASE = 'https://dash.xenarch.dev/sites/claim'

interface Props {
  domain: string
  pluginAdminUrl: string
  errorMessage?: string | null
}

/**
 * First-run screen. Mirrors the dashboard's auth-card visual rhythm:
 * Space-Grotesk title, secondary-color description, primary CTA. The
 * plugin itself does no wallet work — the popup happens on
 * dash.xenarch.dev where AppKit already lives.
 */
export function Onboarding({ domain, pluginAdminUrl, errorMessage }: Props) {
  const [redirecting, setRedirecting] = useState(false)

  const connect = useCallback(() => {
    setRedirecting(true)
    const url = new URL(DASHBOARD_CLAIM_BASE)
    url.searchParams.set('domain', domain)
    url.searchParams.set('integration', 'wp')
    url.searchParams.set('callback', pluginAdminUrl)
    window.location.href = url.toString()
  }, [domain, pluginAdminUrl])

  return (
    <div className="onboarding">
      <div className="onboarding-title">Connect this site to your Xenarch account</div>
      <div className="onboarding-desc">
        Pairing is one click. You'll sign in on <code>dash.xenarch.dev</code>,
        confirm <b>{domain || 'this site'}</b>, and come right back.
        Payout wallet, pricing rules, and gating live in the dashboard —
        the plugin enforces what you set there.
      </div>

      {errorMessage ? (
        <div className="onboarding-error">{errorMessage}</div>
      ) : null}

      <div>
        <button className="btn primary" onClick={connect} disabled={redirecting}>
          {redirecting ? 'Redirecting…' : 'Connect with Xenarch'}
        </button>
      </div>

      <div className="onboarding-footer">
        No Xenarch account yet?{' '}
        <a href="https://dash.xenarch.dev/auth" target="_blank" rel="noopener noreferrer">
          Sign up with your wallet →
        </a>
      </div>
    </div>
  )
}

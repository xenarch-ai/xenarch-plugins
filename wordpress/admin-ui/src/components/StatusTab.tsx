import type { Settings } from '../types'

interface Props {
  settings: Settings
}

type ConnectionState = 'connected' | 'pending' | 'disconnected'

function deriveState(settings: Settings): ConnectionState {
  if (settings.has_site) return 'connected'
  if (settings.has_wallet) return 'pending'
  return 'disconnected'
}

export function StatusTab({ settings }: Props) {
  const state = deriveState(settings)

  const tokenPreview = settings.site_token
    ? `${settings.site_token.slice(0, 8)}...${settings.site_token.slice(-4)}`
    : ''

  const pillClass =
    state === 'connected' ? 'xn-status-pill--ok' :
    state === 'pending'   ? 'xn-status-pill--pending' :
                            'xn-status-pill--err'

  const pillLabel =
    state === 'connected' ? 'connected' :
    state === 'pending'   ? 'pending' :
                            'disconnected'

  /* Server-side detection works locally even in pending state */
  const serverSideActive = state === 'connected' || state === 'pending'

  /* l.js only loads when fully connected (needs site token) */
  const ljsActive = state === 'connected'

  /* URLs only available when fully connected */
  const hasUrls = state === 'connected'

  return (
    <div className="xn-status-card">
      {/* status */}
      <div className="xn-data-row">
        <span className="xn-data-key">status</span>
        <span className="xn-data-val">
          <span className={`xn-status-pill ${pillClass}`}>{pillLabel}</span>
        </span>
      </div>

      {/* site token */}
      <div className="xn-data-row">
        <span className="xn-data-key">site token</span>
        <span className="xn-data-val">
          {state === 'connected' && tokenPreview
            ? tokenPreview
            : state === 'pending'
              ? <span className="xn-dash">registering...</span>
              : <span className="xn-dash">&mdash;</span>
          }
        </span>
      </div>

      {/* server-side */}
      <div className="xn-data-row">
        <span className="xn-data-key">server-side</span>
        <span className="xn-data-val">
          {serverSideActive ? (
            <>
              <span className="xn-dot xn-dot--green" />
              Active &mdash; {settings.bot_signature_count} bot signatures
            </>
          ) : (
            <>
              <span className="xn-dot xn-dot--red" />
              Inactive
            </>
          )}
        </span>
      </div>

      {/* l.js */}
      <div className="xn-data-row">
        <span className="xn-data-key">l.js</span>
        <span className="xn-data-val">
          {ljsActive ? (
            <>
              <span className="xn-dot xn-dot--green" />
              Loading on frontend
            </>
          ) : (
            <>
              <span className="xn-dot xn-dot--red" />
              Not active
            </>
          )}
        </span>
      </div>

      {/* pay.json */}
      <div className="xn-data-row">
        <span className="xn-data-key">pay.json</span>
        <span className="xn-data-val">
          {hasUrls && settings.pay_json_url ? (
            <a href={settings.pay_json_url} target="_blank" rel="noopener noreferrer">
              {settings.pay_json_url}
            </a>
          ) : (
            <span className="xn-dash">&mdash;</span>
          )}
        </span>
      </div>

      {/* xenarch.md */}
      <div className="xn-data-row xn-data-row--last">
        <span className="xn-data-key">xenarch.md</span>
        <span className="xn-data-val">
          {hasUrls && settings.xenarch_md_url ? (
            <a href={settings.xenarch_md_url} target="_blank" rel="noopener noreferrer">
              {settings.xenarch_md_url}
            </a>
          ) : (
            <span className="xn-dash">&mdash;</span>
          )}
        </span>
      </div>
    </div>
  )
}

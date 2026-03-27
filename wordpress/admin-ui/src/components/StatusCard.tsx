import type { Settings } from '../types'

interface Props {
  settings: Settings
}

export function StatusCard({ settings }: Props) {
  const tokenPreview = settings.site_token
    ? `${settings.site_token.slice(0, 8)}...${settings.site_token.slice(-4)}`
    : ''

  return (
    <div className="xenarch-card">
      <h3>Status</h3>
      <table className="xenarch-status-table">
        <tbody>
          <tr>
            <td>Connection</td>
            <td>
              <span className="xenarch-dot xenarch-dot--green" />
              Connected
            </td>
          </tr>
          <tr>
            <td>Email</td>
            <td>{settings.email}</td>
          </tr>
          <tr>
            <td>Site Token</td>
            <td><code>{tokenPreview}</code></td>
          </tr>
          <tr>
            <td>l.js</td>
            <td>
              {settings.has_site ? (
                <>
                  <span className="xenarch-dot xenarch-dot--green" />
                  Loading on frontend
                </>
              ) : (
                <>
                  <span className="xenarch-dot xenarch-dot--red" />
                  Not active
                </>
              )}
            </td>
          </tr>
          <tr>
            <td>Server-side gating</td>
            <td>
              {settings.has_site ? (
                <>
                  <span className="xenarch-dot xenarch-dot--green" />
                  Active — blocking {settings.bot_signature_count} bot signatures
                </>
              ) : (
                <>
                  <span className="xenarch-dot xenarch-dot--red" />
                  Inactive
                </>
              )}
            </td>
          </tr>
          <tr>
            <td>pay.json</td>
            <td>
              {settings.has_site ? (
                <a href={settings.pay_json_url} target="_blank" rel="noopener noreferrer">
                  {settings.pay_json_url}
                </a>
              ) : (
                <>
                  <span className="xenarch-dot xenarch-dot--red" />
                  Not available
                </>
              )}
            </td>
          </tr>
          <tr>
            <td>xenarch.md</td>
            <td>
              {settings.has_site ? (
                <a href={settings.xenarch_md_url} target="_blank" rel="noopener noreferrer">
                  {settings.xenarch_md_url}
                </a>
              ) : (
                <>
                  <span className="xenarch-dot xenarch-dot--red" />
                  Not available
                </>
              )}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  )
}

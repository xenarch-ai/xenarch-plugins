import { useEffect, useState } from 'react'
import type { Settings } from '../types'
import { fetchSiteWallets, setPayoutWallet, type SiteWallet } from '../api'

interface Props {
  settings: Settings
}

function truncate(w: string) {
  return `${w.slice(0, 6)}...${w.slice(-4)}`
}

// XEN-435 P4: the gate's receiving wallet is the merchant identity's default
// linked wallet. When more than one wallet is linked the admin can pick which
// one receives; wallets still in their post-link cooldown can't be chosen yet.
export function WalletBar({ settings }: Props) {
  const [copied, setCopied] = useState(false)
  const [wallets, setWallets] = useState<SiteWallet[] | null>(null)
  const [current, setCurrent] = useState<string | null>(settings.payout_wallet)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    fetchSiteWallets()
      .then((r) => {
        setWallets(r.wallets)
        const def = r.wallets.find((w) => w.is_default)
        if (def) setCurrent(def.address)
      })
      .catch(() => setWallets(null))
  }, [])

  const handleCopy = async (addr: string) => {
    try {
      await navigator.clipboard.writeText(addr)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    } catch {
      // Clipboard API may fail in some contexts.
    }
  }

  const handleSelect = async (addr: string) => {
    if (!addr || addr === current) return
    setBusy(true)
    setError(null)
    try {
      const r = await setPayoutWallet(addr)
      setCurrent(r.payout_wallet)
      const ws = await fetchSiteWallets()
      setWallets(ws.wallets)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to update payout wallet')
    } finally {
      setBusy(false)
    }
  }

  const wallet = current

  if (!wallet) {
    return (
      <div className="xenarch-wallet-bar">
        <span className="xenarch-dot xenarch-dot--red" />
        No payout wallet configured.{' '}
        Link a wallet in the dashboard to start receiving.
      </div>
    )
  }

  // Only offer a selector when there's more than one linked wallet.
  const showSelector = (wallets?.length ?? 0) > 1

  return (
    <div className="xenarch-wallet-bar">
      <span className="xenarch-dot xenarch-dot--green" />
      {showSelector ? (
        <select
          className="xenarch-wallet-select"
          value={current ?? ''}
          disabled={busy}
          onChange={(e) => handleSelect(e.target.value)}
        >
          {(wallets ?? []).map((w) => (
            <option key={w.address} value={w.address} disabled={!w.eligible}>
              {truncate(w.address)}
              {w.is_default ? ' — receiving' : ''}
              {!w.eligible ? ' (in cooldown)' : ''}
            </option>
          ))}
        </select>
      ) : (
        <span className="xenarch-wallet-address">{truncate(wallet)}</span>
      )}
      <button
        className="xenarch-copy-btn"
        onClick={() => handleCopy(wallet)}
        title="Copy address"
      >
        {copied ? 'Copied!' : 'Copy'}
      </button>
      <span className="xenarch-wallet-label">
        {busy ? 'Updating…' : error ? error : 'Payout wallet'}
      </span>
    </div>
  )
}

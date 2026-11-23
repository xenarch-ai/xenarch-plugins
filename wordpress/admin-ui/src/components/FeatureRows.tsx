// XEN-684 — the two feature switches, shared by the first-run chooser and
// the Features tab. Same .master + .toggle primitives as the Gate section.

export type FeatureKey = 'gating' | 'checkout'

interface Props {
  gating: boolean
  checkout: boolean
  wooActive: boolean
  busy?: boolean
  onToggle: (key: FeatureKey) => void
}

interface RowProps {
  label: string
  desc: string
  on: boolean
  disabled: boolean
  onToggle: () => void
}

function FeatureRow({ label, desc, on, disabled, onToggle }: RowProps) {
  const fire = () => { if (!disabled) onToggle() }
  return (
    <div className="master">
      <div className="left">
        <div className="master-label">{label}</div>
        <div className="master-desc">{desc}</div>
      </div>
      <div
        className={`toggle${on ? '' : ' off'}${disabled ? ' is-disabled' : ''}`}
        role="switch"
        aria-checked={on}
        aria-disabled={disabled}
        aria-label={label}
        tabIndex={disabled ? -1 : 0}
        onClick={fire}
        onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fire() } }}
      >
        <div className="dot" />
      </div>
    </div>
  )
}

export function FeatureRows({ gating, checkout, wooActive, busy = false, onToggle }: Props) {
  return (
    <>
      <FeatureRow
        label="Bot gating"
        desc="Charge AI bots per page with HTTP 402. Search engines and people are never charged."
        on={gating}
        disabled={busy}
        onToggle={() => onToggle('gating')}
      />
      <FeatureRow
        label="Crypto checkout (WooCommerce)"
        desc={
          wooActive
            ? 'Let buyers pay the order total in USDC on Base at your WooCommerce checkout.'
            : 'Needs WooCommerce. Install and activate WooCommerce to turn this on.'
        }
        on={checkout && wooActive}
        disabled={busy || !wooActive}
        onToggle={() => onToggle('checkout')}
      />
    </>
  )
}

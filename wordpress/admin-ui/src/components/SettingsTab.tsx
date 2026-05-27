import { useEffect, useState, useCallback } from 'react'
import type { Settings, SiteDetail, GatedCategories, PricingRule, BotCategoryKey } from '../types'
import * as api from '../api'

interface Props {
  // Plugin-local snapshot — only used for the dashboard deep-link.
  // Live data on this tab comes from /xenarch/v1/site/* via fetchSite.
  settings: Settings
  onSettingsChange: (s: Settings) => void
}

// Silence "declared but never read" — onSettingsChange is part of the
// stable prop contract App.tsx hands every tab, even when this tab
// happens not to mutate the plugin-local snapshot.
function useUnused(..._args: unknown[]): void {}

const CATEGORY_LABEL: Record<BotCategoryKey, string> = {
  ai_search:     'AI Search',
  ai_assistants: 'AI Assistants',
  ai_agents:     'AI Agents',
  ai_training:   'AI Training',
  scrapers:      'Scrapers',
  general_ai:    'General AI',
}

const CATEGORY_DESC: Record<BotCategoryKey, string> = {
  ai_search:     'Search engines that index your content so AI can find it.',
  ai_assistants: 'Chatbots asking questions on behalf of a user.',
  ai_agents:     'Autonomous agents performing tasks on your site.',
  ai_training:   'Crawlers harvesting data for model training.',
  scrapers:      'Generic data-extraction bots.',
  general_ai:    'AI crawlers with mixed or unclear intent.',
}

const CATEGORY_ORDER: BotCategoryKey[] = [
  'ai_search', 'ai_assistants', 'ai_agents', 'ai_training', 'scrapers', 'general_ai',
]

const DASHBOARD_WALLET_URL = 'https://dash.xenarch.dev/account/wallet'

export function SettingsTab({ settings, onSettingsChange }: Props) {
  useUnused(settings, onSettingsChange)
  const [site, setSite] = useState<SiteDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [savingSection, setSavingSection] = useState<'gating' | 'pricing' | null>(null)
  const [saveError, setSaveError] = useState<string | null>(null)

  // Editable local copies of the live state.
  const [gateEnabled, setGateEnabled] = useState(true)
  const [usePubDefaults, setUsePubDefaults] = useState(false)
  const [cats, setCats] = useState<GatedCategories | null>(null)

  const [defaultPrice, setDefaultPrice] = useState('0.003')
  const [rules, setRules] = useState<PricingRule[]>([])

  // Hydrate from platform on mount.
  useEffect(() => {
    setLoading(true)
    api
      .fetchSite()
      .then((s) => {
        setSite(s)
        setGateEnabled(s.gating_enabled)
        setUsePubDefaults(s.use_publisher_defaults)
        setCats(s.gated_categories)
        setDefaultPrice(s.default_price_usd)
        setRules(s.rules)
        setLoading(false)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Failed to load settings.')
        setLoading(false)
      })
  }, [])

  const toggleCat = useCallback(
    (k: BotCategoryKey) => {
      if (!cats || usePubDefaults) return
      setCats({ ...cats, [k]: !cats[k] })
    },
    [cats, usePubDefaults],
  )

  const saveGating = useCallback(async () => {
    if (!cats) return
    setSavingSection('gating')
    setSaveError(null)
    try {
      const next = await api.putGating({
        gating_enabled: gateEnabled,
        gated_categories: cats,
        use_publisher_defaults: usePubDefaults,
      })
      setGateEnabled(next.gating_enabled)
      setCats(next.gated_categories)
      setUsePubDefaults(next.use_publisher_defaults)
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : 'Save failed.')
    }
    setSavingSection(null)
  }, [gateEnabled, cats, usePubDefaults])

  const savePricing = useCallback(async () => {
    setSavingSection('pricing')
    setSaveError(null)
    try {
      const cleanRules = rules.filter((r) => r.path && r.path.trim().length > 0)
      await api.putPricing({
        default_price_usd: parseFloat(defaultPrice) || 0,
        default_billing_scope: site?.default_billing_scope ?? 'page',
        rules: cleanRules,
      })
      // Refresh from server so we see normalized state.
      const refreshed = await api.fetchSite()
      setSite(refreshed)
      setDefaultPrice(refreshed.default_price_usd)
      setRules(refreshed.rules)
    } catch (err) {
      setSaveError(err instanceof Error ? err.message : 'Save failed.')
    }
    setSavingSection(null)
  }, [defaultPrice, rules, site])

  const addRule = useCallback(() => {
    setRules([...rules, { path: '/**/example/**', price_usd: '0.01', billing_scope: 'page' }])
  }, [rules])

  const updateRule = useCallback(
    (i: number, patch: Partial<PricingRule>) => {
      setRules(rules.map((r, idx) => (idx === i ? { ...r, ...patch } : r)))
    },
    [rules],
  )

  const removeRule = useCallback(
    (i: number) => {
      setRules(rules.filter((_, idx) => idx !== i))
    },
    [rules],
  )

  if (loading) {
    return <div className="xenarch-section"><p className="xenarch-section-desc">Loading settings…</p></div>
  }
  if (error) {
    return (
      <div className="xenarch-section">
        <h2 className="xenarch-section-title">Settings</h2>
        <div className="xenarch-onboarding-error">{error}</div>
      </div>
    )
  }

  return (
    <>
      {/* Gating */}
      <section className="xenarch-section">
        <h2 className="xenarch-section-title">Gate</h2>
        <p className="xenarch-section-desc">
          What gets charged. Toggles save to your Xenarch account — the same
          state shows on dash.xenarch.dev/sites/{site?.id?.slice(0, 8) ?? '…'}.
        </p>

        <label style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', margin: '0.75rem 0' }}>
          <input
            type="checkbox"
            checked={gateEnabled}
            onChange={(e) => setGateEnabled(e.target.checked)}
          />
          <span><b>Gate AI bots</b> — master toggle. Off = let everything through.</span>
        </label>

        <label style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', margin: '0.75rem 0', opacity: 0.85 }}>
          <input
            type="checkbox"
            checked={usePubDefaults}
            onChange={(e) => setUsePubDefaults(e.target.checked)}
          />
          <span>Inherit category toggles from your account-wide defaults.</span>
        </label>

        <div style={{ marginTop: '0.75rem', opacity: usePubDefaults ? 0.5 : 1 }}>
          {CATEGORY_ORDER.map((k) => (
            <div key={k} style={{ display: 'flex', alignItems: 'center', padding: '0.5rem 0', borderTop: '1px solid var(--xn-border, rgba(255,255,255,0.06))' }}>
              <input
                type="checkbox"
                checked={!!cats?.[k]}
                onChange={() => toggleCat(k)}
                disabled={usePubDefaults}
                style={{ marginRight: '0.75rem' }}
              />
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: '13px', fontWeight: 500 }}>{CATEGORY_LABEL[k]}</div>
                <div style={{ fontSize: '11px', opacity: 0.6 }}>{CATEGORY_DESC[k]}</div>
              </div>
              <span style={{ fontSize: '11px', opacity: 0.6, fontFamily: "'JetBrains Mono', monospace" }}>
                {cats?.[k] ? 'charge' : 'allow'}
              </span>
            </div>
          ))}
        </div>

        <div style={{ marginTop: '1rem', display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
          <button
            className="xenarch-btn xenarch-btn--primary"
            onClick={saveGating}
            disabled={savingSection === 'gating'}
          >
            {savingSection === 'gating' ? 'Saving…' : 'Save gating'}
          </button>
          {saveError && savingSection === null && (
            <span className="xenarch-onboarding-error" style={{ marginLeft: '0.5rem' }}>{saveError}</span>
          )}
        </div>
      </section>

      {/* Pricing */}
      <section className="xenarch-section">
        <h2 className="xenarch-section-title">Pricing</h2>
        <p className="xenarch-section-desc">
          What gated bots pay per request. Rules match first-wins by path
          pattern; anything unmatched falls through to the default.
        </p>

        <label style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', margin: '0.75rem 0' }}>
          <span style={{ minWidth: '120px' }}>Default price</span>
          <span>$</span>
          <input
            type="number"
            step="0.001"
            min="0"
            max="1"
            value={defaultPrice}
            onChange={(e) => setDefaultPrice(e.target.value)}
            style={{ width: '6rem', fontFamily: "'JetBrains Mono', monospace" }}
          />
          <span style={{ opacity: 0.6 }}>per page</span>
        </label>

        <div style={{ marginTop: '0.75rem' }}>
          <div style={{ fontSize: '11px', opacity: 0.6, textTransform: 'uppercase', marginBottom: '0.5rem' }}>URL rules</div>
          {rules.length === 0 && (
            <div style={{ fontSize: '12px', opacity: 0.6 }}>No path rules. All paths use the default price.</div>
          )}
          {rules.map((r, i) => (
            <div key={i} style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginBottom: '0.5rem' }}>
              <input
                type="text"
                value={r.path}
                onChange={(e) => updateRule(i, { path: e.target.value })}
                placeholder="/**/docs/**"
                style={{ flex: 1, fontFamily: "'JetBrains Mono', monospace" }}
              />
              <span>→ $</span>
              <input
                type="number"
                step="0.001"
                min="0"
                max="1"
                value={r.price_usd}
                onChange={(e) => updateRule(i, { price_usd: e.target.value })}
                style={{ width: '5rem', fontFamily: "'JetBrains Mono', monospace" }}
              />
              <button className="xenarch-btn" onClick={() => removeRule(i)}>×</button>
            </div>
          ))}
          <button className="xenarch-btn" onClick={addRule} style={{ marginTop: '0.25rem' }}>
            + add rule
          </button>
        </div>

        <div style={{ marginTop: '1rem' }}>
          <button
            className="xenarch-btn xenarch-btn--primary"
            onClick={savePricing}
            disabled={savingSection === 'pricing'}
          >
            {savingSection === 'pricing' ? 'Saving…' : 'Save pricing'}
          </button>
        </div>
      </section>

      {/* Wallet */}
      <section className="xenarch-section">
        <h2 className="xenarch-section-title">Wallet</h2>
        <p className="xenarch-section-desc">
          Where your earnings land. Managed in the dashboard so the email
          confirm-by-link flow only lives in one place.
        </p>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginTop: '0.75rem' }}>
          <span className="xenarch-dot xenarch-dot--green" />
          <span style={{ fontFamily: "'JetBrains Mono', monospace", fontSize: '13px' }}>
            {site?.payout_wallet
              ? `${site.payout_wallet.slice(0, 6)}…${site.payout_wallet.slice(-4)}`
              : 'not set'}
          </span>
          <span style={{ fontSize: '11px', opacity: 0.6 }}>{site?.payout_network ?? 'base'}</span>
          <a
            href={DASHBOARD_WALLET_URL}
            target="_blank"
            rel="noopener noreferrer"
            style={{ marginLeft: 'auto', fontSize: '12px' }}
          >
            Change in dashboard →
          </a>
        </div>
      </section>
    </>
  )
}

import { useEffect, useState, useCallback } from 'react'
import type {
  Settings,
  SiteDetail,
  GatedCategories,
  PricingRule,
  BotCategoryKey,
} from '../types'
import * as api from '../api'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
}

const CATEGORY_META: { key: BotCategoryKey; name: string; desc: string }[] = [
  { key: 'ai_search',     name: 'AI Search',     desc: 'Index your site so AI can recommend it' },
  { key: 'ai_assistants', name: 'AI Assistants', desc: 'Users asking AI about your content' },
  { key: 'ai_agents',     name: 'AI Agents',     desc: 'Autonomous AI performing tasks on your site' },
  { key: 'ai_training',   name: 'AI Training',   desc: 'Crawlers collecting data for model training' },
  { key: 'scrapers',      name: 'Scrapers',      desc: 'Automated data extraction tools' },
  { key: 'general_ai',    name: 'General AI',    desc: 'Other AI crawlers with mixed or unclear intent' },
]

const DEFAULT_CATEGORIES: GatedCategories = {
  ai_search: false,
  ai_assistants: true,
  ai_agents: true,
  ai_training: true,
  scrapers: true,
  general_ai: true,
}

// Silence "unused" — onSettingsChange is part of the stable tab prop
// contract App hands every tab even when not mutated.
function noop(..._args: unknown[]): void {}

export function SettingsTab({ settings, onSettingsChange }: Props) {
  noop(settings, onSettingsChange)

  const [site, setSite] = useState<SiteDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [savingGating, setSavingGating] = useState(false)
  const [savingPricing, setSavingPricing] = useState(false)
  const [gatingMsg, setGatingMsg] = useState<string | null>(null)
  const [pricingMsg, setPricingMsg] = useState<string | null>(null)

  const [useInherit, setUseInherit] = useState(true)
  const [masterOn, setMasterOn] = useState(true)
  const [cats, setCats] = useState<GatedCategories>(DEFAULT_CATEGORIES)
  const [gatingDirty, setGatingDirty] = useState(false)

  const [defaultPrice, setDefaultPrice] = useState('0.003')
  const [defaultScope, setDefaultScope] = useState<'page' | 'path'>('page')
  const [rules, setRules] = useState<(PricingRule & { id: number })[]>([])
  const [pricingDirty, setPricingDirty] = useState(false)

  // Hydrate.
  useEffect(() => {
    setLoading(true)
    api
      .fetchSite()
      .then((s) => {
        setSite(s)
        setUseInherit(s.use_publisher_defaults)
        setMasterOn(s.gating_enabled)
        setCats({ ...DEFAULT_CATEGORIES, ...s.gated_categories })
        setDefaultPrice(s.default_price_usd)
        setDefaultScope(s.default_billing_scope)
        setRules(s.rules.map((r, i) => ({ ...r, id: Date.now() + i })))
        setLoading(false)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Failed to load settings.')
        setLoading(false)
      })
  }, [])

  // Gating: master toggle + per-cat.
  const toggleMaster = useCallback(() => {
    if (useInherit) return
    setMasterOn((v) => !v)
    setGatingDirty(true); setGatingMsg(null)
  }, [useInherit])

  const toggleCat = useCallback(
    (k: BotCategoryKey) => {
      if (useInherit) return
      setCats((c) => ({ ...c, [k]: !c[k] }))
      setGatingDirty(true); setGatingMsg(null)
    },
    [useInherit],
  )

  const saveGating = useCallback(async () => {
    setSavingGating(true); setGatingMsg(null)
    try {
      const next = await api.putGating({
        gating_enabled: masterOn,
        gated_categories: cats,
        use_publisher_defaults: useInherit,
      })
      setMasterOn(next.gating_enabled)
      setCats({ ...DEFAULT_CATEGORIES, ...next.gated_categories })
      setUseInherit(next.use_publisher_defaults)
      setGatingDirty(false)
      setGatingMsg('Saved.')
    } catch (err) {
      setGatingMsg(err instanceof Error ? err.message : 'Save failed.')
    }
    setSavingGating(false)
  }, [masterOn, cats, useInherit])

  const flipInherit = useCallback(async () => {
    setSavingGating(true); setGatingMsg(null)
    try {
      const next = await api.putGating({
        gating_enabled: masterOn,
        gated_categories: cats,
        use_publisher_defaults: !useInherit,
      })
      setUseInherit(next.use_publisher_defaults)
      setMasterOn(next.gating_enabled)
      setCats({ ...DEFAULT_CATEGORIES, ...next.gated_categories })
      setGatingDirty(false)
    } catch (err) {
      setGatingMsg(err instanceof Error ? err.message : 'Could not switch.')
    }
    setSavingGating(false)
  }, [masterOn, cats, useInherit])

  // Pricing.
  const savePricing = useCallback(async () => {
    setSavingPricing(true); setPricingMsg(null)
    try {
      const cleaned: PricingRule[] = rules
        .filter((r) => r.path && r.path.trim())
        .map((r) => ({ path: r.path, price_usd: r.price_usd, billing_scope: r.billing_scope }))
      await api.putPricing({
        default_price_usd: parseFloat(defaultPrice) || 0,
        default_billing_scope: defaultScope,
        rules: cleaned,
      })
      const refreshed = await api.fetchSite()
      setSite(refreshed)
      setDefaultPrice(refreshed.default_price_usd)
      setDefaultScope(refreshed.default_billing_scope)
      setRules(refreshed.rules.map((r, i) => ({ ...r, id: Date.now() + i })))
      setPricingDirty(false)
      setPricingMsg('Saved.')
    } catch (err) {
      setPricingMsg(err instanceof Error ? err.message : 'Save failed.')
    }
    setSavingPricing(false)
  }, [defaultPrice, defaultScope, rules])

  if (loading) return <div className="empty">Loading settings…</div>
  if (error) {
    return (
      <div className="section">
        <div className="section-head">
          <div className="section-title">Settings</div>
        </div>
        <div className="onboarding-error">{error}</div>
      </div>
    )
  }

  return (
    <>
      {/* Inherit/customize banner — XEN-369 */}
      <div className="inherit-banner">
        <div>
          <div className="b-title">
            {useInherit ? 'Inheriting global gating settings' : 'Custom gating for this site'}
          </div>
          <div className="b-desc">
            {useInherit
              ? 'This site uses your account-wide defaults. Toggling them on dash.xenarch.dev cascades here.'
              : "This site has its own gating settings. Click 'Use global settings' to revert to your account defaults."}
          </div>
        </div>
        <button className="btn secondary" disabled={savingGating} onClick={flipInherit}>
          {savingGating ? 'Switching…' : useInherit ? 'Customise for this site' : 'Use global settings'}
        </button>
      </div>

      {/* Gate */}
      <div className="section">
        <div className="section-head">
          <div className="section-title">Gate</div>
          <div className="section-desc">
            {useInherit
              ? 'Read-only view of your global gating. Edit on dash.xenarch.dev.'
              : 'What gets gated on this site.'}
          </div>
        </div>

        <div className="master" style={useInherit ? { opacity: 0.65 } : undefined}>
          <div className="left">
            <div className="master-label">Gate AI bots</div>
            <div className="master-desc">All non-human traffic must pay unless allowed below.</div>
          </div>
          <div
            className={`toggle${masterOn ? '' : ' off'}`}
            onClick={toggleMaster}
            style={useInherit ? { cursor: 'not-allowed' } : undefined}
          >
            <div className="dot" />
          </div>
        </div>

        <div className="bot-types" style={useInherit ? { opacity: 0.65 } : undefined}>
          {CATEGORY_META.map((c) => (
            <div key={c.key} className="bot-type">
              <div
                className={`cat-toggle ${cats[c.key] ? 'on' : 'off'}`}
                onClick={() => toggleCat(c.key)}
                style={useInherit ? { cursor: 'not-allowed' } : undefined}
              >
                <div className="dot" />
              </div>
              <div className="cat-info">
                <div className="cat-name">{c.name}</div>
                <div className="cat-desc">{c.desc}</div>
              </div>
              <div className={`cat-stat ${cats[c.key] ? 'on' : 'off'}`}>
                {cats[c.key] ? 'gated' : 'allowed'}
              </div>
            </div>
          ))}
        </div>

        <div className="always-note">
          Search engines (Google, Bing) and social previews always pass through free.
        </div>

        <div className="actions">
          {!useInherit ? (
            <button
              className="btn primary"
              onClick={saveGating}
              disabled={!gatingDirty || savingGating}
            >
              {savingGating ? 'Saving…' : 'Save gating'}
            </button>
          ) : null}
          {gatingMsg ? <span className="muted">{gatingMsg}</span> : null}
        </div>
      </div>

      {/* Pricing */}
      <div className="section">
        <div className="section-head">
          <div className="section-title">Pricing</div>
          <div className="section-desc">Default price for any gated request. Per-path rules override the default.</div>
        </div>

        <div className="row">
          <label>Default price</label>
          <span className="symbol">$</span>
          <input
            type="text"
            className="price"
            value={defaultPrice}
            onChange={(e) => { setDefaultPrice(e.target.value); setPricingDirty(true); setPricingMsg(null) }}
          />
          <div className="action-seg">
            <button
              className={`action-btn${defaultScope === 'page' ? ' active' : ''}`}
              onClick={() => { setDefaultScope('page'); setPricingDirty(true); setPricingMsg(null) }}
            >per page</button>
            <button
              className={`action-btn${defaultScope === 'path' ? ' active' : ''}`}
              onClick={() => { setDefaultScope('path'); setPricingDirty(true); setPricingMsg(null) }}
            >per path</button>
          </div>
        </div>

        <div style={{ marginTop: 12 }}>
          {rules.map((r) => (
            <div key={r.id} className="rule">
              <span className="grip">⠇</span>
              <span className="match">URL contains</span>
              <input
                type="text"
                className="path"
                value={r.path}
                onChange={(e) => {
                  setRules((prev) => prev.map((x) => (x.id === r.id ? { ...x, path: e.target.value } : x)))
                  setPricingDirty(true)
                }}
              />
              <span className="arrow">→</span>
              <span className="dollar">$</span>
              <input
                type="text"
                className="price"
                value={r.price_usd}
                onChange={(e) => {
                  setRules((prev) => prev.map((x) => (x.id === r.id ? { ...x, price_usd: e.target.value } : x)))
                  setPricingDirty(true)
                }}
              />
              <div className="action-seg">
                <button
                  className={`action-btn${r.billing_scope === 'page' ? ' active' : ''}`}
                  onClick={() => {
                    setRules((prev) => prev.map((x) => (x.id === r.id ? { ...x, billing_scope: 'page' } : x)))
                    setPricingDirty(true)
                  }}
                >per page</button>
                <button
                  className={`action-btn${r.billing_scope === 'path' ? ' active' : ''}`}
                  onClick={() => {
                    setRules((prev) => prev.map((x) => (x.id === r.id ? { ...x, billing_scope: 'path' } : x)))
                    setPricingDirty(true)
                  }}
                >per path</button>
              </div>
              <button
                className="remove"
                onClick={() => {
                  setRules((prev) => prev.filter((x) => x.id !== r.id))
                  setPricingDirty(true)
                }}
              >×</button>
            </div>
          ))}
          <button
            className="btn secondary"
            onClick={() => {
              setRules((prev) => [
                ...prev,
                { id: Date.now(), path: '', price_usd: '0.01', billing_scope: 'page' },
              ])
              setPricingDirty(true)
            }}
          >+ add rule</button>
        </div>

        <div className="actions">
          <button
            className="btn primary"
            onClick={savePricing}
            disabled={!pricingDirty || savingPricing}
          >
            {savingPricing ? 'Saving…' : 'Save pricing'}
          </button>
          {pricingMsg ? <span className="muted">{pricingMsg}</span> : null}
        </div>
      </div>

      {/* Wallet — read-only deeplink */}
      <div className="section">
        <div className="section-head">
          <div className="section-title">Wallet</div>
          <div className="section-desc">
            Where your earnings land. Managed in the dashboard so the email-confirm
            flow only lives in one place.
          </div>
        </div>
        <div className="wallet-card" style={{ marginBottom: 0 }}>
          <span className="dot" />
          <span className="addr">
            {site?.payout_wallet
              ? `${site.payout_wallet.slice(0, 6)}…${site.payout_wallet.slice(-4)}`
              : 'not set'}
          </span>
          <span className="label">{site?.payout_network ?? 'base'}</span>
          <a
            className="change"
            href="https://dash.xenarch.dev/account/wallet"
            target="_blank"
            rel="noopener noreferrer"
          >
            Change in dashboard →
          </a>
        </div>
      </div>
    </>
  )
}

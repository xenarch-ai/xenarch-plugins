import { useState, useMemo, useCallback, useEffect } from 'react'
import type { BotCategoryKey, BotAction, BotCategories, BotOverrides, BotSignature } from '../types'

interface Props {
  initialCategory: BotCategoryKey | 'all'
  signatures: BotSignature[]
  categories: BotCategories
  overrides: BotOverrides
  onOverrideChange: (updates: Record<string, BotAction | null>) => void
  onClose: () => void
}

const PILLS: { key: BotCategoryKey | 'all'; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'ai_search', label: 'AI Search' },
  { key: 'ai_assistants', label: 'Assistants' },
  { key: 'ai_agents', label: 'Agents' },
  { key: 'ai_training', label: 'Training' },
  { key: 'scrapers', label: 'Scrapers' },
  { key: 'general_ai', label: 'General' },
]

function timeAgo(dateStr: string | null): string {
  if (!dateStr) return ''
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

function isNew(dateStr: string | null): boolean {
  if (!dateStr) return false
  return Date.now() - new Date(dateStr).getTime() < 7 * 24 * 60 * 60 * 1000
}

export function BotDetailModal({ initialCategory, signatures, categories, overrides, onOverrideChange, onClose }: Props) {
  const [activeFilter, setActiveFilter] = useState<BotCategoryKey | 'all'>(initialCategory)
  const [searchQuery, setSearchQuery] = useState('')
  const [selected, setSelected] = useState<Set<string>>(new Set())

  // Lock body scroll when modal is open.
  useEffect(() => {
    document.body.style.overflow = 'hidden'
    return () => { document.body.style.overflow = '' }
  }, [])

  const filteredBots = useMemo(() => {
    let list = signatures
    if (activeFilter !== 'all') {
      list = list.filter(s => s.category === activeFilter)
    }
    if (searchQuery) {
      const q = searchQuery.toLowerCase()
      list = list.filter(s => s.name.toLowerCase().includes(q) || s.company.toLowerCase().includes(q))
    }
    // Sort by last_seen descending (freshest first), nulls last.
    return [...list].sort((a, b) => {
      if (!a.last_seen && !b.last_seen) return 0
      if (!a.last_seen) return 1
      if (!b.last_seen) return -1
      return new Date(b.last_seen).getTime() - new Date(a.last_seen).getTime()
    })
  }, [signatures, activeFilter, searchQuery])

  const getEffectiveAction = useCallback((sig: BotSignature): BotAction => {
    if (sig.name in overrides) return overrides[sig.name] as BotAction
    return categories[sig.category] ?? 'charge'
  }, [overrides, categories])

  const handleBotAction = (sigName: string, newAction: BotAction) => {
    const sig = signatures.find(s => s.name === sigName)
    if (!sig) return
    const categoryDefault = categories[sig.category] || 'charge'
    if (newAction === categoryDefault) {
      // Clicking back to default removes the override.
      onOverrideChange({ [sigName]: null })
    } else {
      onOverrideChange({ [sigName]: newAction })
    }
  }

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelected(new Set(filteredBots.map(b => b.name)))
    } else {
      setSelected(new Set())
    }
  }

  const handleSelectOne = (name: string, checked: boolean) => {
    const next = new Set(selected)
    if (checked) next.add(name)
    else next.delete(name)
    setSelected(next)
  }

  const handleBulkAction = (action: BotAction) => {
    if (selected.size === 0) return
    const updates: Record<string, BotAction | null> = {}
    selected.forEach(name => {
      const sig = signatures.find(s => s.name === name)
      if (!sig) return
      const categoryDefault = categories[sig.category] || 'charge'
      updates[name] = action === categoryDefault ? null : action
    })
    onOverrideChange(updates)
    setSelected(new Set())
  }

  const allSelected = filteredBots.length > 0 && filteredBots.every(b => selected.has(b.name))

  return (
    <div className="xenarch-modal-overlay" onClick={e => { if (e.target === e.currentTarget) onClose() }}>
      <div className="xenarch-modal">
        <div className="xenarch-modal-top">
          <div className="xenarch-modal-header">
            <div className="xenarch-modal-title">Manage bots</div>
            <button className="xenarch-modal-close" onClick={onClose}>&times;</button>
          </div>

          <div className="xenarch-cat-pills">
            {PILLS.map(p => (
              <button
                key={p.key}
                className={`xenarch-cat-pill ${activeFilter === p.key ? 'xenarch-cat-pill--active' : ''}`}
                onClick={() => { setActiveFilter(p.key); setSelected(new Set()) }}
              >
                {p.label}
              </button>
            ))}
          </div>

          <input
            type="text"
            className="xenarch-modal-search"
            placeholder="Search bots..."
            value={searchQuery}
            onChange={e => setSearchQuery(e.target.value)}
          />
        </div>

        <div className="xenarch-bulk-bar">
          <div className="xenarch-bulk-left">
            <input
              type="checkbox"
              className="xenarch-checkbox"
              checked={allSelected}
              onChange={e => handleSelectAll(e.target.checked)}
            />
            <span className="xenarch-bulk-label">Select all</span>
          </div>
          <div className="xenarch-bulk-actions">
            <button className="xenarch-bulk-btn" onClick={() => handleBulkAction('allow')}>Allow selected</button>
            <button className="xenarch-bulk-btn" onClick={() => handleBulkAction('charge')}>Charge selected</button>
          </div>
        </div>

        <div className="xenarch-modal-body">
          {filteredBots.map(sig => {
            const action = getEffectiveAction(sig)
            return (
              <div key={sig.name} className="xenarch-bot-row">
                <input
                  type="checkbox"
                  className="xenarch-checkbox"
                  checked={selected.has(sig.name)}
                  onChange={e => handleSelectOne(sig.name, e.target.checked)}
                />
                <div className="xenarch-bot-info">
                  <span className="xenarch-bot-name">{sig.name}</span>
                  <span className="xenarch-bot-company">{sig.company}</span>
                  {isNew(sig.last_seen) && <span className="xenarch-bot-new">new</span>}
                </div>
                {sig.last_seen && (
                  <span className="xenarch-bot-seen">{timeAgo(sig.last_seen)}</span>
                )}
                <div className="xenarch-action-seg">
                  <button
                    className={`xenarch-action-btn ${action === 'allow' ? 'xenarch-action-btn--active xenarch-action-btn--allow' : ''}`}
                    onClick={() => handleBotAction(sig.name, 'allow')}
                  >
                    allow
                  </button>
                  <button
                    className={`xenarch-action-btn ${action === 'charge' ? 'xenarch-action-btn--active' : ''}`}
                    onClick={() => handleBotAction(sig.name, 'charge')}
                  >
                    charge
                  </button>
                </div>
              </div>
            )
          })}
          {filteredBots.length === 0 && (
            <div className="xenarch-empty">No bots found</div>
          )}
        </div>
      </div>
    </div>
  )
}

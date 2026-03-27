import type { PricingRule } from '../types'

interface Props {
  index: number
  rule: PricingRule
  onChange: (index: number, rule: PricingRule) => void
  onDelete: (index: number) => void
  onMove: (from: number, to: number) => void
}

export function PricingRuleRow({ index, rule, onChange, onDelete, onMove }: Props) {

  const handleDragStart = (e: React.DragEvent) => {
    e.dataTransfer.setData('text/plain', String(index))
    e.dataTransfer.effectAllowed = 'move'
  }

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
    e.dataTransfer.dropEffect = 'move'
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    const from = parseInt(e.dataTransfer.getData('text/plain'), 10)
    if (!isNaN(from) && from !== index) {
      onMove(from, index)
    }
  }

  return (
    <div
      className="xenarch-rule-row"
      draggable
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDrop={handleDrop}
    >
      <span style={{ cursor: 'grab', color: '#646970', userSelect: 'none' }} title="Drag to reorder">
        &#x2630;
      </span>
      <input
        className="xenarch-input xenarch-rule-path"
        type="text"
        value={rule.path_contains}
        onChange={(e) => onChange(index, { ...rule, path_contains: e.target.value })}
        placeholder="Path contains (e.g. /premium)"
      />
      <input
        className="xenarch-input xenarch-rule-price"
        type="number"
        value={rule.price_usd}
        onChange={(e) => onChange(index, { ...rule, price_usd: e.target.value })}
        step="0.001"
        min="0"
        max="1"
        placeholder="$"
      />
      <button
        className="xenarch-btn xenarch-btn--danger"
        onClick={() => onDelete(index)}
        title="Delete rule"
      >
        &times;
      </button>
    </div>
  )
}

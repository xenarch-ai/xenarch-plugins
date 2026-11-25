// XEN-714: what the store's checkout takes, in words, from the networks it
// has turned on (WooStatus.networks) plus USDC on Base, which is always on.
// "USDC on Base, Ethereum and BNB Chain, and USDT on Tron".
import type { WooStatus } from './types'

type Takes = Pick<WooStatus, 'networks' | 'network_choices'>

const COINS = ['USDC', 'USDT'] as const

function names(list: string[]): string {
  if (list.length <= 1) return list.join('')
  return `${list.slice(0, -1).join(', ')} and ${list[list.length - 1]}`
}

/** Network display names, per coin, in the order the plugin lists them. */
export function takenByCoin(w: Takes | null | undefined): Array<{ coin: string; networks: string[] }> {
  const choices = w?.network_choices || []
  const on = w?.networks || {}
  const nameOf = (key: string) => choices.find((c) => c.key === key)?.name || (key === 'base' ? 'Base' : key)
  const order = ['base', ...choices.map((c) => c.key).filter((k) => k !== 'base')]
  for (const k of Object.keys(on)) if (!order.includes(k)) order.push(k)
  return COINS.map((coin) => {
    const nets = order.filter((k) => (coin === 'USDC' && k === 'base') || !!on[k]?.includes(coin))
    return { coin, networks: nets.map(nameOf) }
  }).filter((g) => g.networks.length > 0)
}

/** "USDC on Base and Ethereum, and USDT on Tron". */
export function takesLine(w: Takes | null | undefined, opts: { evmOnly?: boolean } = {}): string {
  const groups = takenByCoin(w)
    .map((g) => ({ coin: g.coin, networks: opts.evmOnly ? g.networks.filter((n) => n !== 'Tron') : g.networks }))
    .filter((g) => g.networks.length > 0)
  const parts = groups.map((g) => `${g.coin} on ${names(g.networks)}`)
  return parts.length > 1 ? `${parts.slice(0, -1).join(', ')}, and ${parts[parts.length - 1]}` : parts.join('')
}

/** "USDC" or "USDC and USDT". */
export function coinsLine(w: Takes | null | undefined): string {
  return names(takenByCoin(w).map((g) => g.coin))
}

/** True when USDT on Tron is on. */
export function takesTron(w: Takes | null | undefined): boolean {
  return !!w?.networks?.tron?.includes('USDT')
}

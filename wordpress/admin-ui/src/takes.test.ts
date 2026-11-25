// XEN-714: the checkout's coins and networks, in words.
import { describe, expect, it } from 'vitest'
import { coinsLine, takesLine, takesTron } from './takes'

const CHOICES = [
  { key: 'ethereum', name: 'Ethereum', coins: ['USDC', 'USDT'] },
  { key: 'bnb', name: 'BNB Chain', coins: ['USDC', 'USDT'] },
  { key: 'monad', name: 'Monad', coins: ['USDC', 'USDT'] },
  { key: 'tron', name: 'Tron', coins: ['USDT'] },
]

describe('takesLine', () => {
  it('is USDC on Base when nothing else is on', () => {
    expect(takesLine({ networks: {}, network_choices: CHOICES })).toBe('USDC on Base')
    expect(coinsLine({ networks: {}, network_choices: CHOICES })).toBe('USDC')
    expect(takesLine(null)).toBe('USDC on Base')
  })

  it('names every coin and network the store turned on', () => {
    const w = { networks: { ethereum: ['USDC', 'USDT'], bnb: ['USDC'], tron: ['USDT'] }, network_choices: CHOICES }
    expect(takesLine(w)).toBe('USDC on Base, Ethereum and BNB Chain, and USDT on Ethereum and Tron')
    expect(coinsLine(w)).toBe('USDC and USDT')
    expect(takesTron(w)).toBe(true)
  })

  it('leaves Tron out for the EVM receiving address', () => {
    const w = { networks: { tron: ['USDT'] }, network_choices: CHOICES }
    expect(takesLine(w, { evmOnly: true })).toBe('USDC on Base')
    expect(takesLine(w)).toBe('USDC on Base, and USDT on Tron')
  })
})

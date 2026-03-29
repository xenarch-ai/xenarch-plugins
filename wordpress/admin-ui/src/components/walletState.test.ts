import { describe, expect, it } from 'vitest'
import type { Settings } from '../types'
import {
  applyWalletConnection,
  applyWalletDisconnect,
  isWalletSectionConnected,
  shouldCaptureConnectedWallet,
} from './walletState'

const baseSettings: Settings = {
  api_key: true,
  site_id: 'site_123',
  site_token: 'token_123',
  default_price: '0.003',
  payout_wallet: '',
  gate_unknown_traffic: '1',
  gate_enabled: '1',
  bot_categories: { ai_search: 'allow', ai_assistants: 'charge', ai_agents: 'charge', ai_training: 'charge', scrapers: 'charge', general_ai: 'charge' },
  bot_overrides: {},
  wallet_type: '',
  wallet_network: 'base',
  domain: 'example.com',
  has_wallet: false,
  has_site: true,
  bot_signature_count: 42,
  pay_json_url: 'https://example.com/.well-known/pay.json',
  xenarch_md_url: 'https://example.com/.well-known/xenarch.md',
}

describe('walletState', () => {
  it('treats the section as connected when AppKit is live even before settings persist', () => {
    expect(
      isWalletSectionConnected(
        baseSettings,
        { isConnected: true, address: '0x1234567890123456789012345678901234567890' },
        false
      )
    ).toBe(true)
  })

  it('suppresses the connected UI while a disconnect is in progress', () => {
    expect(
      isWalletSectionConnected(
        {
          ...baseSettings,
          payout_wallet: '0x1234567890123456789012345678901234567890',
          wallet_type: 'walletconnect',
        },
        { isConnected: true, address: '0x1234567890123456789012345678901234567890' },
        true
      )
    ).toBe(false)
  })

  it('captures a new live wallet connection once per new address', () => {
    expect(
      shouldCaptureConnectedWallet({
        settings: baseSettings,
        account: { isConnected: true, address: '0x1234567890123456789012345678901234567890' },
        savedAddress: '',
        disconnecting: false,
      })
    ).toBe(true)

    expect(
      shouldCaptureConnectedWallet({
        settings: {
          ...baseSettings,
          payout_wallet: '0x1234567890123456789012345678901234567890',
          wallet_type: 'walletconnect',
        },
        account: { isConnected: true, address: '0x1234567890123456789012345678901234567890' },
        savedAddress: '0x1234567890123456789012345678901234567890',
        disconnecting: false,
      })
    ).toBe(false)
  })

  it('does not recapture the same address while disconnecting', () => {
    expect(
      shouldCaptureConnectedWallet({
        settings: baseSettings,
        account: { isConnected: true, address: '0x1234567890123456789012345678901234567890' },
        savedAddress: '',
        disconnecting: true,
      })
    ).toBe(false)
  })

  it('builds the saved wallet settings for connect and disconnect transitions', () => {
    expect(
      applyWalletConnection(baseSettings, '0x1234567890123456789012345678901234567890')
    ).toMatchObject({
      payout_wallet: '0x1234567890123456789012345678901234567890',
      wallet_type: 'walletconnect',
    })

    expect(
      applyWalletDisconnect({
        ...baseSettings,
        payout_wallet: '0x1234567890123456789012345678901234567890',
        wallet_type: 'walletconnect',
      })
    ).toMatchObject({
      payout_wallet: '',
      wallet_type: '',
    })
  })
})

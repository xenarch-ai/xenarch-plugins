import type { Settings } from '../types'

export interface WalletAccountSnapshot {
  address?: string
  isConnected: boolean
}

export function applyWalletConnection(settings: Settings, address: string): Settings {
  return {
    ...settings,
    payout_wallet: address,
    wallet_type: 'connected',
  }
}

export function applyWalletDisconnect(settings: Settings): Settings {
  return {
    ...settings,
    payout_wallet: '',
    wallet_type: '',
  }
}

export function hasLiveWalletConnection(account: WalletAccountSnapshot): account is WalletAccountSnapshot & {
  address: string
} {
  return Boolean(account.isConnected && account.address)
}

export function isWalletSectionConnected(
  settings: Settings,
  account: WalletAccountSnapshot,
  disconnecting: boolean
): boolean {
  if (disconnecting) {
    return false
  }

  return hasLiveWalletConnection(account) || settings.wallet_type === 'connected'
}

interface ShouldCaptureConnectedWalletArgs {
  settings: Settings
  account: WalletAccountSnapshot
  savedAddress: string
  disconnecting: boolean
}

export function shouldCaptureConnectedWallet({
  settings,
  account,
  savedAddress,
  disconnecting,
}: ShouldCaptureConnectedWalletArgs): boolean {
  if (disconnecting || !hasLiveWalletConnection(account)) {
    return false
  }

  return account.address !== savedAddress || settings.wallet_type !== 'connected'
}

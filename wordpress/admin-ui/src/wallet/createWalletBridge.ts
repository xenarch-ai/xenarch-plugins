/**
 * Hidden iframe bridge for Coinbase wallet creation.
 *
 * Same pattern as Reown AppKit: visible modal in the page DOM,
 * hidden iframe to api.xenarch.dev for the actual Coinbase SDK calls.
 * Communication via postMessage.
 */

const PLATFORM_BASE = 'https://api.xenarch.dev'
const BRIDGE_TIMEOUT_MS = 15000

export interface WalletBridge {
  sendOtp(email: string): Promise<{ flowId: string }>
  verifyOtp(flowId: string, otp: string): Promise<{ address: string; isNewUser: boolean }>
  destroy(): void
}

/**
 * Create a hidden iframe bridge to the Coinbase auth page.
 * Returns a Promise that resolves when the bridge is ready.
 */
export async function createWalletBridge(siteToken: string): Promise<WalletBridge> {
  // Step 1: Get session URL from platform
  const res = await fetch(`${PLATFORM_BASE}/v1/wallet-session`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Site-Token': siteToken,
    },
  })

  if (!res.ok) throw new Error('Failed to create wallet session')
  const { url } = await res.json()
  if (!url) throw new Error('No session URL returned')

  // Step 2: Create hidden iframe
  const iframe = document.createElement('iframe')
  iframe.src = url
  iframe.style.cssText = 'position:absolute;width:0;height:0;border:none;opacity:0;pointer-events:none;'
  document.body.appendChild(iframe)

  const expectedOrigin = new URL(PLATFORM_BASE).origin

  // Step 3: Wait for bridge-ready
  await new Promise<void>((resolve, reject) => {
    const timeout = setTimeout(() => {
      cleanup()
      reject(new Error('Bridge initialization timed out'))
    }, BRIDGE_TIMEOUT_MS)

    function handler(event: MessageEvent) {
      if (event.origin !== expectedOrigin) return
      if (event.data?.type === 'xenarch-bridge-ready') {
        cleanup()
        resolve()
      }
      if (event.data?.type === 'xenarch-bridge-error') {
        cleanup()
        reject(new Error(event.data.error || 'Bridge init failed'))
      }
    }

    function cleanup() {
      clearTimeout(timeout)
      window.removeEventListener('message', handler)
    }

    window.addEventListener('message', handler)
  })

  // Step 4: Return bridge interface
  function postToIframe(msg: Record<string, unknown>) {
    iframe.contentWindow?.postMessage(msg, expectedOrigin)
  }

  function waitForMessage(successType: string, errorType: string): Promise<MessageEvent['data']> {
    return new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        cleanup()
        reject(new Error('Operation timed out'))
      }, 30000)

      function handler(event: MessageEvent) {
        if (event.origin !== expectedOrigin) return
        if (event.data?.type === successType) {
          cleanup()
          resolve(event.data)
        }
        if (event.data?.type === errorType) {
          cleanup()
          reject(new Error(event.data.error || 'Operation failed'))
        }
      }

      function cleanup() {
        clearTimeout(timeout)
        window.removeEventListener('message', handler)
      }

      window.addEventListener('message', handler)
    })
  }

  return {
    async sendOtp(email: string) {
      const promise = waitForMessage('xenarch-otp-sent', 'xenarch-otp-error')
      postToIframe({ type: 'xenarch-send-otp', email })
      const data = await promise
      return { flowId: data.flowId }
    },

    async verifyOtp(flowId: string, otp: string) {
      const promise = waitForMessage('xenarch-wallet-created', 'xenarch-verify-error')
      postToIframe({ type: 'xenarch-verify-otp', flowId, otp })
      const data = await promise
      return { address: data.address, isNewUser: data.isNewUser ?? false }
    },

    destroy() {
      if (iframe.parentNode) {
        iframe.parentNode.removeChild(iframe)
      }
    },
  }
}

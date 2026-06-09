<?php
/**
 * Xenarch payment proof verification — Joomla port.
 *
 * 1:1 mirror of the WordPress Xenarch_Payment_Proof. Validates an on-chain
 * `tx_hash` against the platform for a given gate. Verified results are
 * cached (via the Joomla cache layer) to avoid hitting the platform on
 * every replay of the same paid request.
 *
 * Canonical headers (must match the SDK middleware):
 *   X-Xenarch-Gate-Id  — UUID of the gate the agent paid for
 *   X-Xenarch-Tx-Hash  — 0x-prefixed Base USDC transferWithAuthorization hash
 *
 * Vanilla x402 (C10 / XEN-457):
 *   X-PAYMENT          — base64 of a signed EIP-3009 authorization
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

class PaymentProof
{
    public const TX_HASH_HEADER = 'X-Xenarch-Tx-Hash';
    public const GATE_ID_HEADER = 'X-Xenarch-Gate-Id';

    private const CACHE_PREFIX = 'xenarch_paid_';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Read the canonical Xenarch payment headers from the current request.
     *
     * @return array{gate_id:string,tx_hash:string}|null
     *         Both values present and well-formed, or null if either is missing.
     */
    public static function extractPaymentProof(): ?array
    {
        $txHash = self::readHeader('HTTP_X_XENARCH_TX_HASH', self::TX_HASH_HEADER);
        $gateId = self::readHeader('HTTP_X_XENARCH_GATE_ID', self::GATE_ID_HEADER);

        if ($txHash === null || $gateId === null) {
            return null;
        }

        $txHash = strtolower(trim($txHash));
        $gateId = strtolower(trim($gateId));

        if (preg_match('/^0x[0-9a-f]{64}$/', $txHash) !== 1) {
            return null;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $gateId) !== 1) {
            return null;
        }

        return ['gate_id' => $gateId, 'tx_hash' => $txHash];
    }

    /**
     * Read the vanilla x402 ``X-PAYMENT`` header (C10 / XEN-457).
     *
     * A third-party x402 agent that knows nothing about Xenarch pays with
     * the standard ``X-PAYMENT`` voucher (base64 of a signed EIP-3009
     * authorization) instead of the canonical (gate_id, tx_hash) pair. The
     * gate hands the raw header to the platform's settle-x402 endpoint.
     *
     * @return string|null The raw base64 header value, or null if absent.
     */
    public static function extractXPayment(): ?string
    {
        $xPayment = self::readHeader('HTTP_X_PAYMENT', 'X-PAYMENT');
        if ($xPayment === null) {
            return null;
        }
        $xPayment = trim($xPayment);
        return $xPayment === '' ? null : $xPayment;
    }

    /**
     * Verify a tx hash satisfies the named gate.
     *
     * @param string $gateId  Gate UUID, as supplied by the agent in the X-Xenarch-Gate-Id header.
     * @param string $txHash  Lower-case 0x-prefixed transaction hash.
     * @return bool True if the platform confirms the payment.
     */
    public static function verify(string $gateId, string $txHash): bool
    {
        if ($txHash === '' || $gateId === '') {
            return false;
        }

        $cacheKey = self::CACHE_PREFIX . md5($txHash . '|' . $gateId);

        $cached = Cache::get($cacheKey);
        if ($cached === 'valid') {
            return true;
        }
        if ($cached === 'invalid') {
            return false;
        }

        try {
            $result = (new ApiClient())->verifyPayment($gateId, $txHash);
        } catch (ApiException $e) {
            // XEN-386: only fail-open on transport errors (timeout, DNS,
            // connection refused) and 5xx. A 4xx is the platform deliberately
            // saying "no" (e.g. rejecting a forged tx hash) and MUST fail
            // closed, otherwise a bad proof would silently serve content.
            if ($e->isClientError()) {
                // Cache 'invalid' briefly so we don't hammer the platform,
                // but short enough that an operator fix takes effect fast.
                Cache::set($cacheKey, 'invalid', 60);
                return false;
            }
            // Transport error or 5xx — fail open so a brief platform outage
            // doesn't block real paid agents. Short cache TTL to recover fast.
            Cache::set($cacheKey, 'valid', 60);
            return true;
        }

        // Platform returns {gate_id, status, tx_hash, amount_usd, verified_at}.
        // Treat status="paid" as the canonical success signal; keep
        // 'verified'/'valid' fallbacks so the plugin survives minor schema drift.
        $status = isset($result['status']) ? strtolower((string) $result['status']) : '';
        $isValid = $status === 'paid'
            || !empty($result['verified'])
            || !empty($result['valid']);
        Cache::set($cacheKey, $isValid ? 'valid' : 'invalid', self::CACHE_TTL);

        return $isValid;
    }

    /**
     * Read a header by both $_SERVER key and getallheaders() name (case-insensitive).
     */
    private static function readHeader(string $serverKey, string $headerName): ?string
    {
        if (isset($_SERVER[$serverKey]) && $_SERVER[$serverKey] !== '') {
            return (string) $_SERVER[$serverKey];
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                $lower = strtolower($headerName);
                foreach ($headers as $name => $value) {
                    if (strtolower((string) $name) === $lower) {
                        return (string) $value;
                    }
                }
            }
        }

        return null;
    }
}

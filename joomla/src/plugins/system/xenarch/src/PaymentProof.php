<?php
/**
 * Xenarch payment proof verification — Joomla port.
 *
 * Validates an on-chain `tx_hash` against the platform for a given gate.
 * Verified hashes are cached in #__xenarch_cache to avoid hitting the
 * platform on every replay of the same paid request.
 *
 * Replaces the old access-token (JWT) flow now that the platform does
 * stateless on-chain re-verification (post-XEN-179).
 *
 * Canonical headers (must match the SDK middleware):
 *   X-Xenarch-Gate-Id  — UUID of the gate the agent paid for
 *   X-Xenarch-Tx-Hash  — 0x-prefixed Base USDC transferWithAuthorization hash
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

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

        $cached = self::getCached($cacheKey);
        if ($cached === 'valid') {
            return true;
        }
        if ($cached === 'invalid') {
            return false;
        }

        $api = new ApiClient();
        $result = $api->verifyPayment($gateId, $txHash);

        if ($result === null) {
            // Platform unreachable — fail open briefly to avoid blocking legitimate
            // paid requests during platform downtime. Short cache to recover quickly.
            self::setCached($cacheKey, 'valid', 60);
            return true;
        }

        // Platform returns {gate_id, status, tx_hash, amount_usd, verified_at}.
        // Treat status="paid" as the canonical success signal; keep
        // 'verified'/'valid' fallbacks so the plugin survives minor schema drift.
        $status = isset($result['status']) ? strtolower((string) $result['status']) : '';
        $isValid = $status === 'paid'
            || !empty($result['verified'])
            || !empty($result['valid']);
        self::setCached($cacheKey, $isValid ? 'valid' : 'invalid', self::CACHE_TTL);

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

    /**
     * Get a cached value from #__xenarch_cache.
     */
    private static function getCached(string $key): ?string
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('cache_value'))
                ->from($db->quoteName('#__xenarch_cache'))
                ->where($db->quoteName('cache_key') . ' = ' . $db->quote($key))
                ->where($db->quoteName('expires_at') . ' > NOW()');
            $db->setQuery($query);
            return $db->loadResult();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set a cached value in #__xenarch_cache.
     */
    private static function setCached(string $key, string $value, int $ttl): void
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttl);

            $query = "INSERT INTO " . $db->quoteName('#__xenarch_cache') . " ("
                . $db->quoteName('cache_key') . ', '
                . $db->quoteName('cache_value') . ', '
                . $db->quoteName('expires_at')
                . ") VALUES ("
                . $db->quote($key) . ', '
                . $db->quote($value) . ', '
                . $db->quote($expiresAt)
                . ") ON DUPLICATE KEY UPDATE "
                . $db->quoteName('cache_value') . ' = VALUES(' . $db->quoteName('cache_value') . '), '
                . $db->quoteName('expires_at') . ' = VALUES(' . $db->quoteName('expires_at') . ')';

            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            // Silently fail — cache is best-effort.
        }
    }
}

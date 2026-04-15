<?php
/**
 * Xenarch access token verification — Joomla port.
 *
 * Validates bearer tokens against the Xenarch platform API.
 * Verified tokens are cached in #__xenarch_cache to avoid
 * hitting the API on every request.
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

class AccessToken
{
    private const CACHE_PREFIX = 'xenarch_token_';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Check if an Authorization header contains a JWT-like bearer token.
     */
    public static function hasValidBearerFormat(?string $authHeader): bool
    {
        if (empty($authHeader) || stripos($authHeader, 'Bearer ') !== 0) {
            return false;
        }

        $token = trim(substr($authHeader, 7));
        if (empty($token)) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)?$/', $token) === 1;
    }

    /**
     * Extract the bearer token string from an Authorization header.
     */
    public static function extractToken(?string $authHeader): ?string
    {
        if (empty($authHeader) || stripos($authHeader, 'Bearer ') !== 0) {
            return null;
        }
        $token = trim(substr($authHeader, 7));
        return empty($token) ? null : $token;
    }

    /**
     * Verify a bearer token is valid (paid gate receipt).
     */
    public static function verifyToken(string $token, string $url = ''): bool
    {
        if (empty($token)) {
            return false;
        }

        if (preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)?$/', $token) !== 1) {
            return false;
        }

        // Cache key includes URL for per-page/path scoping.
        $cacheKey = self::CACHE_PREFIX . md5($token . '|' . $url);

        // Check cache table.
        $cached = self::getCached($cacheKey);
        if ($cached === 'valid') {
            return true;
        }
        if ($cached === 'invalid') {
            return false;
        }

        // Verify against platform API.
        $params = ComponentHelper::getParams('com_xenarch');
        $siteToken = $params->get('site_token', '');
        if (empty($siteToken)) {
            return false;
        }

        $api = new ApiClient();
        $result = $api->verifyAccessToken($token, $url);

        if ($result === null) {
            // API unreachable — fail open.
            self::setCached($cacheKey, 'valid', 60);
            return true;
        }

        $isValid = !empty($result['valid']);
        self::setCached($cacheKey, $isValid ? 'valid' : 'invalid', self::CACHE_TTL);

        return $isValid;
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

            // Upsert.
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

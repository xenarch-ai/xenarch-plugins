<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Component\Xenarch\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

/**
 * Helper for reading and writing Xenarch component parameters.
 *
 * Joomla stores component settings in the `params` JSON column of
 * `#__extensions`. This helper wraps get/set operations with the
 * same key names used in the WordPress plugin's wp_options.
 */
class XenarchHelper
{
    /**
     * Get a single parameter value.
     */
    public static function getParam(string $key, string $default = ''): string
    {
        $params = ComponentHelper::getParams('com_xenarch');
        return $params->get($key, $default);
    }

    /**
     * Get all parameters as an associative array.
     */
    public static function getAllParams(): array
    {
        $params = ComponentHelper::getParams('com_xenarch');
        return $params->toArray();
    }

    /**
     * Set a single parameter value and persist to the database.
     */
    public static function setParam(string $key, string $value): void
    {
        $params = ComponentHelper::getParams('com_xenarch');
        $params->set($key, $value);
        self::saveParams($params);
    }

    /**
     * Set multiple parameters at once and persist to the database.
     */
    public static function setParams(array $values): void
    {
        $params = ComponentHelper::getParams('com_xenarch');
        foreach ($values as $key => $value) {
            $params->set($key, $value);
        }
        self::saveParams($params);
    }

    /**
     * Persist the params Registry to the database.
     */
    private static function saveParams(Registry $params): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_xenarch'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $db->setQuery($query);
        $db->execute();

        // Invalidate cached params so subsequent reads get the new values.
        ComponentHelper::getParams('com_xenarch', true);
    }

    /**
     * Get a JSON parameter decoded as an array.
     */
    public static function getJsonParam(string $key, string $default = '{}'): array
    {
        $raw = self::getParam($key, $default);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get the site domain from Joomla configuration.
     */
    public static function getSiteDomain(): string
    {
        $siteUrl = \Joomla\CMS\Uri\Uri::root();
        $parsed = parse_url($siteUrl, PHP_URL_HOST);
        return $parsed ?: '';
    }

    /**
     * Get the site URL (without trailing slash).
     */
    public static function getSiteUrl(): string
    {
        return rtrim(\Joomla\CMS\Uri\Uri::root(), '/');
    }

    /**
     * Get a cached value from #__xenarch_cache.
     */
    public static function getCached(string $key): ?string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('cache_value'))
            ->from($db->quoteName('#__xenarch_cache'))
            ->where($db->quoteName('cache_key') . ' = ' . $db->quote($key))
            ->where($db->quoteName('expires_at') . ' > NOW()');
        $db->setQuery($query);
        $result = $db->loadResult();
        return $result ?: null;
    }

    /**
     * Set a cached value in #__xenarch_cache.
     */
    public static function setCached(string $key, string $value, int $ttlSeconds = 86400): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $query = "INSERT INTO " . $db->quoteName('#__xenarch_cache')
            . " (" . $db->quoteName('cache_key') . ", " . $db->quoteName('cache_value') . ", " . $db->quoteName('expires_at') . ")"
            . " VALUES (" . $db->quote($key) . ", " . $db->quote($value) . ", " . $db->quote($expiresAt) . ")"
            . " ON DUPLICATE KEY UPDATE"
            . " " . $db->quoteName('cache_value') . " = " . $db->quote($value)
            . ", " . $db->quoteName('expires_at') . " = " . $db->quote($expiresAt);

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Get WalletConnect project ID (cached from platform API).
     */
    public static function getWcProjectId(): string
    {
        $cached = self::getCached('wc_project_id');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $api = new \Xenarch\Plugin\System\Xenarch\ApiClient();
            $result = $api->getConfig();
            if ($result !== null && !empty($result['wc_project_id'])) {
                self::setCached('wc_project_id', $result['wc_project_id'], 86400);
                return $result['wc_project_id'];
            }
        } catch (\Throwable $e) {
            // Silently fail — return empty.
        }

        return '';
    }

    /**
     * Get Coinbase CDP project ID (cached from platform API).
     */
    public static function getCdpProjectId(): string
    {
        $cached = self::getCached('cdp_project_id');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $api = new \Xenarch\Plugin\System\Xenarch\ApiClient();
            $result = $api->getConfig();
            if ($result !== null && !empty($result['cdp_project_id'])) {
                self::setCached('cdp_project_id', $result['cdp_project_id'], 86400);
                return $result['cdp_project_id'];
            }
        } catch (\Throwable $e) {
            // Silently fail — return empty.
        }

        return '';
    }
}

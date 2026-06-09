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
}

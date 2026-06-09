<?php
/**
 * Xenarch transient cache — Joomla port.
 *
 * The thin-window rebuild (XEN-481) keeps nothing canonical in the
 * database, so there is no bespoke #__xenarch_cache table any more. This
 * is the Joomla analogue of the WordPress plugin's transients: short-lived
 * values (gating config, gate payloads, payment-proof results, the pay.json
 * site snapshot) cached through Joomla's own cache layer.
 *
 * Joomla's cache expires entries using the *reading* controller's
 * lifetime, not the lifetime in force when the value was stored — a
 * notorious footgun when different call sites want different TTLs. We
 * sidestep it entirely by embedding our own expiry in the payload and
 * giving the underlying store a single generous backstop lifetime. The
 * logical TTL is always enforced here, regardless of how Joomla evicts.
 *
 * Caching is forced on (``caching => true``) so the gate behaves the same
 * whether or not the site has global caching enabled.
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;

class Cache
{
    private const GROUP = 'com_xenarch';

    /** Physical backstop lifetime (minutes) — must exceed every logical TTL we use. */
    private const BACKSTOP_MINUTES = 60;

    /**
     * Return a cached value, or null on miss / logical expiry.
     *
     * @return mixed
     */
    public static function get(string $key)
    {
        try {
            $raw = self::controller()->get($key);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($raw) || !isset($raw['e'], $raw['d'])) {
            return null;
        }
        if ((int) $raw['e'] < time()) {
            return null; // logically expired
        }
        return $raw['d'];
    }

    /**
     * Store a value under a logical TTL (seconds).
     *
     * @param mixed $value
     */
    public static function set(string $key, $value, int $ttlSeconds): void
    {
        try {
            self::controller()->store(['e' => time() + max(1, $ttlSeconds), 'd' => $value], $key);
        } catch (\Throwable $e) {
            // best-effort cache; ignore.
        }
    }

    public static function remove(string $key): void
    {
        try {
            self::controller()->remove($key);
        } catch (\Throwable $e) {
            // ignore.
        }
    }

    private static function controller()
    {
        return Factory::getContainer()
            ->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', [
                'defaultgroup' => self::GROUP,
                'caching'      => true,
                'lifetime'     => self::BACKSTOP_MINUTES,
            ]);
    }
}

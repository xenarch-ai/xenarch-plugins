<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;

/**
 * Installer script. Post-XEN-481 the plugin is a thin window into the
 * platform: it creates no tables and stores nothing canonical locally. The
 * only thing to seed on install is the browser-proof HMAC secret (plus the
 * two gate behaviour fallbacks the gate reads during a platform outage).
 */
class Com_XenarchInstallerScript
{
    /** Minimum Joomla version required. */
    protected $minimumJoomla = '5.0';

    /** Minimum PHP version required. */
    protected $minimumPhp = '8.1';

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'install') {
            $this->seedParams();
        }

        return true;
    }

    /**
     * Seed the browser-proof secret + outage-fallback toggles on first
     * install. Never overwrites an existing value (so a reinstall keeps the
     * pairing). The platform owns pricing/gating/wallet — none of that is
     * seeded here.
     */
    private function seedParams(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_xenarch'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($query);
        $params = json_decode((string) $db->loadResult(), true) ?: [];

        $defaults = [
            'site_id'              => '',
            'site_token'           => '',
            'gate_enabled'         => '1',
            'gate_unknown_traffic' => '1',
            'browser_proof_secret' => bin2hex(random_bytes(32)),
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($params[$key]) || $params[$key] === '') {
                $params[$key] = $value;
            }
        }

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_xenarch'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($update);
        $db->execute();
    }
}

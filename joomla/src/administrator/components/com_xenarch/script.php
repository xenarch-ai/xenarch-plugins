<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;

class Com_XenarchInstallerScript
{
    /**
     * Minimum Joomla version required.
     */
    protected $minimumJoomla = '5.0';

    /**
     * Minimum PHP version required.
     */
    protected $minimumPhp = '8.1';

    /**
     * Called after install/update to set default component params.
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'install') {
            $this->setDefaultParams();
        }

        // Always ensure COOP header allows popups (required for Coinbase Smart Wallet).
        $this->ensureCoopAllowsPopups();

        return true;
    }

    /**
     * Set default component parameters on first install.
     */
    private function setDefaultParams(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $defaults = [
            'api_key'              => '',
            'site_id'              => '',
            'site_token'           => '',
            'default_price'        => '0.003',
            'payout_wallet'        => '',
            'gate_unknown_traffic' => '1',
            'gate_enabled'         => '1',
            'bot_categories'       => json_encode([
                'ai_search'     => 'allow',
                'ai_assistants' => 'charge',
                'ai_agents'     => 'charge',
                'ai_training'   => 'charge',
                'scrapers'      => 'charge',
                'general_ai'    => 'charge',
            ]),
            'bot_overrides'        => '{}',
            'pricing_rules'        => '[]',
            'wallet_type'          => '',
            'wallet_network'       => 'base',
            'browser_proof_secret' => bin2hex(random_bytes(32)),
            'email'                => '',
        ];

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($defaults)))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_xenarch'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Ensure the HTTP Headers plugin sets Cross-Origin-Opener-Policy to
     * 'same-origin-allow-popups' instead of 'same-origin'. The Coinbase
     * Smart Wallet SDK uses a popup for Google/Apple sign-in that must
     * communicate back to the parent page.
     */
    private function ensureCoopAllowsPopups(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('httpheaders'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));
        $db->setQuery($query);
        $paramsJson = $db->loadResult();

        if ($paramsJson === null) {
            return; // Plugin not installed.
        }

        $params = json_decode($paramsJson, true) ?: [];

        // Only update if coop is not already set to allow popups or disabled.
        $current = $params['coop'] ?? 'same-origin';
        if ($current === 'same-origin') {
            $params['coop'] = 'same-origin-allow-popups';

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
                ->where($db->quoteName('element') . ' = ' . $db->quote('httpheaders'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));
            $db->setQuery($query);
            $db->execute();
        }
    }
}

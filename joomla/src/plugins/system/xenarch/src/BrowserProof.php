<?php
/**
 * Xenarch browser proof helpers — Joomla port.
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;

class BrowserProof
{
    public const COOKIE_NAME = 'xenarch_browser_proof';
    public const DEFAULT_TTL = 900;

    /**
     * Issue a signed cookie value.
     */
    public static function issueCookieValue(string $userAgent, ?int $issuedAt = null): string
    {
        $issuedAt = $issuedAt ?? time();
        $secret = self::getSecret();

        if (empty($secret)) {
            return '';
        }

        $payload = $issuedAt . '|' . hash_hmac('sha256', $issuedAt . '|' . $userAgent, $secret);

        return base64_encode($payload);
    }

    /**
     * Validate a browser proof cookie value.
     */
    public static function validateCookieValue(string $cookieValue, string $userAgent, ?int $currentTime = null): bool
    {
        if (empty($cookieValue) || empty($userAgent)) {
            return false;
        }

        $currentTime = $currentTime ?? time();
        $secret = self::getSecret();

        if (empty($secret)) {
            return false;
        }

        $decoded = base64_decode($cookieValue, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 2) {
            return false;
        }

        $issuedAt = (int) $parts[0];
        $signature = $parts[1];

        if ($issuedAt <= 0 || $issuedAt + self::DEFAULT_TTL < $currentTime) {
            return false;
        }

        $expected = hash_hmac('sha256', $issuedAt . '|' . $userAgent, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Get secret for signing proof cookies.
     */
    private static function getSecret(): string
    {
        // Check environment constant first.
        if (defined('XENARCH_BROWSER_PROOF_SECRET') && XENARCH_BROWSER_PROOF_SECRET) {
            return (string) XENARCH_BROWSER_PROOF_SECRET;
        }

        $params = ComponentHelper::getParams('com_xenarch');
        $secret = $params->get('browser_proof_secret', '');

        if (!empty($secret)) {
            return (string) $secret;
        }

        // Generate a new secret if none exists.
        $secret = bin2hex(random_bytes(32));

        // Persist it.
        $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
        $currentParams = $params->toString();
        $paramsArray = json_decode($currentParams, true) ?: [];
        $paramsArray['browser_proof_secret'] = $secret;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($paramsArray)))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_xenarch'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($query);
        $db->execute();

        return $secret;
    }
}

<?php
/**
 * Xenarch gate response helpers — Joomla port.
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

class GateResponse
{
    /**
     * Build a safe fallback gate payload when upstream gate creation fails.
     */
    public static function buildFallbackGatePayload(string $requestPath, string $detectionMethod): array
    {
        return [
            'xenarch'          => true,
            'requested_path'   => $requestPath,
            'detection_method' => $detectionMethod,
        ];
    }

    /**
     * Build the browser proof challenge page.
     */
    public static function buildChallengeHtml(string $requestPath, string $cookieValue): string
    {
        // Prevent open redirect.
        if (empty($requestPath) || $requestPath[0] !== '/' || str_starts_with($requestPath, '//')) {
            $requestPath = '/';
        }

        $cookieName = BrowserProof::COOKIE_NAME;
        $target = self::jsonEncodeForScript($requestPath);
        $cookie = self::jsonEncodeForScript($cookieValue);
        $cookieNameJs = self::jsonEncodeForScript($cookieName);

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Verifying browser</title></head><body><noscript>JavaScript is required to continue.</noscript>'
            . '<script>(function(){var cookieName=' . $cookieNameJs . ';var cookieValue=' . $cookie . ';var target=' . $target . ';'
            . 'document.cookie=cookieName+"="+cookieValue+"; Path=/; Max-Age=' . (int) BrowserProof::DEFAULT_TTL . '; SameSite=Lax";'
            . 'window.location.replace(target);})();</script>'
            . '<p>Verifying browser...</p></body></html>';
    }

    private static function jsonEncodeForScript(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}

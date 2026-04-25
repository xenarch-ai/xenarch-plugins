<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Xenarch\Plugin\System\Xenarch\ApiClient;
use Xenarch\Plugin\System\Xenarch\BotDetect;
use Xenarch\Plugin\System\Xenarch\BrowserProof;
use Xenarch\Plugin\System\Xenarch\Discovery;
use Xenarch\Plugin\System\Xenarch\GateResponse;
use Xenarch\Plugin\System\Xenarch\PaymentProof;

/**
 * System plugin — intercepts requests for bot detection, payment gating,
 * discovery document serving, and l.js injection.
 *
 * Mirrors the WordPress Xenarch_Gate class behavior exactly.
 */
class Xenarch extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise'  => 'onAfterInitialise',
            'onAfterRoute'       => 'onAfterRoute',
            'onBeforeCompileHead' => 'onBeforeCompileHead',
        ];
    }

    /**
     * Serve /.well-known/ discovery documents before routing.
     */
    public function onAfterInitialise(): void
    {
        $app = $this->getApplication();
        if (!$app instanceof SiteApplication) {
            return;
        }

        $path = $app->getInput()->server->getString('REQUEST_URI', '');
        $path = parse_url($path, PHP_URL_PATH) ?: '';

        if ($path === '/.well-known/pay.json' || $path === '/pay.json') {
            Discovery::servePayJson();
            $app->close();
        }

        if ($path === '/.well-known/xenarch.md') {
            Discovery::serveXenarchMd();
            $app->close();
        }
    }

    /**
     * Bot detection and payment gating — mirrors Xenarch_Gate::maybe_gate_request().
     */
    public function onAfterRoute(): void
    {
        $app = $this->getApplication();

        // Only gate frontend (site) requests.
        if (!$app instanceof SiteApplication) {
            return;
        }

        // No site token configured — plugin not set up yet.
        $params = ComponentHelper::getParams('com_xenarch');
        $siteToken = $params->get('site_token', '');
        if (empty($siteToken)) {
            return;
        }

        // Never gate logged-in users.
        $user = $app->getIdentity();
        if ($user && !$user->guest) {
            return;
        }

        $requestUri = $app->getInput()->server->getString('REQUEST_URI', '');

        // Never gate /.well-known paths (discovery docs).
        if (str_starts_with($requestUri, '/.well-known/') || $requestUri === '/pay.json') {
            return;
        }

        // If the request presents a verified payment proof (gate id + on-chain tx hash), let it through.
        $proof = PaymentProof::extractPaymentProof();
        if ($proof && PaymentProof::verify($proof['gate_id'], $proof['tx_hash'])) {
            return;
        }

        // Check if a pricing rule marks this path as FREE.
        if ($this->isFreePath($requestUri)) {
            return;
        }

        // Master gate toggle.
        if ($params->get('gate_enabled', '1') !== '1') {
            return;
        }

        // Skip static assets.
        $ext = strtolower(pathinfo($requestUri, PATHINFO_EXTENSION));
        $staticExts = ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'css', 'js', 'woff', 'woff2', 'ttf', 'map'];
        if (in_array($ext, $staticExts, true)) {
            return;
        }

        // Run full bot detection.
        $userAgent = $app->getInput()->server->getString('HTTP_USER_AGENT', '');
        $headers = $this->getRequestHeaders();
        $context = $this->getRequestContext($requestUri, $userAgent);
        $detection = BotDetect::detectFull($userAgent, $headers, $context);
        $this->recordDetectionEvent($requestUri, $detection);

        // Allow unknown non-browser traffic through if toggle is off.
        if ($detection['traffic_class'] === 'unknown_non_browser'
            && $params->get('gate_unknown_traffic', '1') !== '1') {
            return;
        }

        if ($detection['decision'] === 'allow') {
            return;
        }

        // Category-based gating.
        if (!$this->shouldGateBot($detection)) {
            return;
        }

        if ($detection['decision'] === 'challenge') {
            $this->renderBrowserChallenge($requestUri, $userAgent, $detection);
            return;
        }

        $this->renderBlockResponse($requestUri, $detection);
    }

    /**
     * Inject l.js on frontend pages (disabled until CDN is live).
     */
    public function onBeforeCompileHead(): void
    {
        // Disabled — CDN not deployed yet.
        // $app = $this->getApplication();
        // if (!$app instanceof SiteApplication) return;
        // $doc = $app->getDocument();
        // $doc->addScript('https://cdn.xenarch.dev/l.js', [], ['type' => 'module']);
    }

    // ------------------------------------------------------------------
    // Private helpers — ported from WordPress Xenarch_Gate
    // ------------------------------------------------------------------

    private function getRequestHeaders(): array
    {
        $headers = [];
        $map = [
            'HTTP_ACCEPT'                   => 'Accept',
            'HTTP_ACCEPT_LANGUAGE'          => 'Accept-Language',
            'HTTP_ACCEPT_ENCODING'          => 'Accept-Encoding',
            'HTTP_SEC_FETCH_MODE'           => 'Sec-Fetch-Mode',
            'HTTP_SEC_FETCH_DEST'           => 'Sec-Fetch-Dest',
            'HTTP_SEC_FETCH_SITE'           => 'Sec-Fetch-Site',
            'HTTP_SEC_FETCH_USER'           => 'Sec-Fetch-User',
            'HTTP_SEC_CH_UA'                => 'Sec-Ch-Ua',
            'HTTP_SEC_CH_UA_MOBILE'         => 'Sec-CH-UA-Mobile',
            'HTTP_SEC_CH_UA_PLATFORM'       => 'Sec-CH-UA-Platform',
            'HTTP_UPGRADE_INSECURE_REQUESTS' => 'Upgrade-Insecure-Requests',
            'HTTP_REFERER'                  => 'Referer',
        ];

        foreach ($map as $serverKey => $headerName) {
            $value = $this->getApplication()->getInput()->server->getString($serverKey, '');
            if ($value !== '') {
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    private function getRequestContext(string $requestUri, string $userAgent): array
    {
        $cookieValue = $this->getApplication()->getInput()->cookie->getString(BrowserProof::COOKIE_NAME, '');

        return [
            'request_method'      => $this->getApplication()->getInput()->getMethod(),
            'has_cookies'         => !empty($_COOKIE),
            'browser_proof_valid' => BrowserProof::validateCookieValue($cookieValue, $userAgent),
            'request_path'        => $requestUri,
            'is_feed'             => false,
            'is_preview'          => false,
        ];
    }

    private function shouldGateBot(array $detection): bool
    {
        if ($detection['method'] !== 'ua_match') {
            return true;
        }

        $signature = !empty($detection['signals'][0]) ? $detection['signals'][0] : '';
        if (empty($signature)) {
            return true;
        }

        if ($detection['traffic_class'] === 'social_preview_fetcher') {
            return false;
        }

        $params = ComponentHelper::getParams('com_xenarch');

        // Check per-bot override first.
        $overrides = json_decode($params->get('bot_overrides', '{}'), true);
        if (is_array($overrides) && isset($overrides[$signature])) {
            return $overrides[$signature] === 'charge';
        }

        // Fall back to category default.
        $category = $detection['category'] ?? BotDetect::getCategoryForSignature($signature);
        if (empty($category)) {
            return true;
        }

        $categories = json_decode($params->get('bot_categories', '{}'), true);
        if (is_array($categories) && isset($categories[$category])) {
            return $categories[$category] === 'charge';
        }

        return true;
    }

    private function isFreePath(string $requestUri): bool
    {
        $matched = $this->matchPricingRule($requestUri);
        return $matched !== null && (string) $matched === '0';
    }

    private function matchPricingRule(string $requestUri): ?string
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $params = ComponentHelper::getParams('com_xenarch');
        $rules = json_decode($params->get('pricing_rules', '[]'), true);

        if (!is_array($rules)) {
            return null;
        }

        foreach ($rules as $rule) {
            if (empty($rule['path_contains']) || !isset($rule['price_usd'])) {
                continue;
            }
            if (str_contains($path, $rule['path_contains'])) {
                return (string) $rule['price_usd'];
            }
        }

        return null;
    }

    private function getOrCreateGate(string $requestUri, string $detectionMethod = 'ua_match'): ?array
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $api = new ApiClient();

        return $api->createGate($path, $detectionMethod);
    }

    private function renderBrowserChallenge(string $requestUri, string $userAgent, array $detection): void
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $cookieValue = BrowserProof::issueCookieValue($userAgent);

        $app = $this->getApplication();
        $app->setHeader('Content-Type', 'text/html; charset=utf-8');
        $app->setHeader('Cache-Control', 'no-store, private');
        $app->setHeader('X-Xenarch-Bot', $detection['method']);
        $app->setHeader('X-Xenarch-Decision', 'challenge');
        http_response_code(403);
        echo GateResponse::buildChallengeHtml($path, $cookieValue);
        $app->close();
    }

    private function renderBlockResponse(string $requestUri, array $detection): void
    {
        $agentLabel = $detection['method'];
        if (in_array($detection['method'], ['ua_match', 'fetcher_ua'], true) && !empty($detection['signals'][0])) {
            $agentLabel = $detection['signals'][0];
        } elseif (str_starts_with($detection['method'], 'header_score')) {
            $agentLabel = 'Unknown Bot';
        }

        $gate = $this->getOrCreateGate($requestUri, $agentLabel);

        if ($gate === null || empty($gate)) {
            $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
            $gate = GateResponse::buildFallbackGatePayload($path, $detection['method']);
        } else {
            if (!isset($gate['xenarch'])) {
                $gate['xenarch'] = true;
            }
        }

        $gate = $this->enrichGatePayload($gate);

        $app = $this->getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $app->setHeader('Cache-Control', 'no-store, private');
        $app->setHeader('X-Xenarch-Bot', $detection['method']);
        $app->setHeader('X-Xenarch-Decision', 'block');

        foreach ($this->getDiscoveryHeaders() as $name => $value) {
            $app->setHeader($name, $value);
        }

        http_response_code(402);
        echo json_encode($gate, JSON_UNESCAPED_SLASHES);
        $app->close();
    }

    private function enrichGatePayload(array $gate): array
    {
        $siteUrl = rtrim(Uri::root(), '/');
        $payJsonUrl = $siteUrl . '/.well-known/pay.json';

        $gate['pay_json_url'] = $payJsonUrl;
        $gate['instructions_url'] = $siteUrl . '/.well-known/xenarch.md';
        $gate['message'] = 'Payment required. Fetch ' . $payJsonUrl . ' for pricing, payment address, and integration tools. Full instructions at ' . $siteUrl . '/.well-known/xenarch.md';

        return $gate;
    }

    private function getDiscoveryHeaders(): array
    {
        $siteUrl = rtrim(Uri::root(), '/');
        $payJsonUrl = $siteUrl . '/.well-known/pay.json';
        $xenarchMd = $siteUrl . '/.well-known/xenarch.md';

        return [
            'Link'       => '<' . $payJsonUrl . '>; rel="payment-terms", <' . $xenarchMd . '>; rel="payment-instructions"',
            'X-Pay-Json' => $payJsonUrl,
            'X-Xenarch'  => 'payment-required; pay_json="' . $payJsonUrl . '"',
        ];
    }

    private function recordDetectionEvent(string $requestUri, array $detection): void
    {
        $this->logBotDetection($detection);
    }

    private function logBotDetection(array $detection): void
    {
        $method = $detection['method'] ?? '';
        if (!in_array($method, ['ua_match', 'fetcher_ua'], true) && !str_starts_with($method, 'header_score')) {
            return;
        }

        $signature = '';
        if (!empty($detection['signals'][0])) {
            $signature = $detection['signals'][0];
        } elseif (str_starts_with($method, 'header_score')) {
            return; // Don't log generic header scores.
        }

        if (empty($signature)) {
            return;
        }

        $category = BotDetect::getCategoryForSignature($signature);
        $company = BotDetect::getCompanyForSignature($signature);

        if (empty($category)) {
            $userAgent = $this->getApplication()->getInput()->server->getString('HTTP_USER_AGENT', '');
            $category = BotDetect::autoCategorize($userAgent);
            $company = $signature;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $now = gmdate('Y-m-d H:i:s');
        $table = $db->quoteName('#__xenarch_bot_log');

        // Atomic upsert.
        $query = "INSERT INTO {$table} ("
            . $db->quoteName('signature') . ', '
            . $db->quoteName('category') . ', '
            . $db->quoteName('company') . ', '
            . $db->quoteName('first_seen') . ', '
            . $db->quoteName('last_seen') . ', '
            . $db->quoteName('hit_count')
            . ') VALUES ('
            . $db->quote($signature) . ', '
            . $db->quote($category) . ', '
            . $db->quote($company) . ', '
            . $db->quote($now) . ', '
            . $db->quote($now) . ', 1'
            . ') ON DUPLICATE KEY UPDATE '
            . $db->quoteName('last_seen') . ' = VALUES(' . $db->quoteName('last_seen') . '), '
            . $db->quoteName('hit_count') . ' = ' . $db->quoteName('hit_count') . ' + 1';

        $db->setQuery($query);
        $db->execute();
    }

}

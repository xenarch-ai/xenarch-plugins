<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Xenarch\Plugin\System\Xenarch\ApiClient;
use Xenarch\Plugin\System\Xenarch\ApiException;
use Xenarch\Plugin\System\Xenarch\BotDetect;
use Xenarch\Plugin\System\Xenarch\BrowserProof;
use Xenarch\Plugin\System\Xenarch\Cache;
use Xenarch\Plugin\System\Xenarch\Discovery;
use Xenarch\Plugin\System\Xenarch\GateResponse;
use Xenarch\Plugin\System\Xenarch\PaymentProof;

/**
 * System plugin — intercepts requests for bot detection, payment gating and
 * discovery-document serving.
 *
 * 1:1 mirror of the WordPress Xenarch_Gate (post-XEN-380 thin-window
 * rewrite). The plugin is a thin enforcement surface: gating policy,
 * pricing, per-bot overrides and the payout wallet all live on the
 * platform and are read live through the site_token-authed surface.
 * Nothing canonical is stored locally.
 *
 * Lifecycle mapping vs. WordPress:
 *   - WP template_redirect (priority 0, discovery)  → onAfterInitialise
 *   - WP template_redirect (priority 1, gating)     → onAfterDispatch
 *
 * The gate runs on onAfterDispatch (not onAfterRoute) on purpose: that is
 * the only point where Joomla has resolved whether the request is a real
 * page or a 404. A not-found route throws during dispatch and never reaches
 * onAfterDispatch, so nonexistent pages are never gated — the Joomla
 * analogue of WordPress's is_404() skip.
 */
class Xenarch extends CMSPlugin implements SubscriberInterface
{
    /** Per-request memo of the gating config so we resolve it at most once. */
    private ?array $gatingConfigRequestCache = null;

    private const GATING_CONFIG_CACHE_KEY = 'xenarch_gating_config_cache';
    private const GATING_CONFIG_TTL = 60;   // seconds
    private const GATE_CACHE_TTL = 1800;    // 30 minutes

    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
            'onAfterDispatch'   => 'onAfterDispatch',
        ];
    }

    /**
     * Serve /.well-known/ discovery documents before routing, and bypass the
     * page cache for x402 payment replays (XEN-384 analogue).
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

        // XEN-384: a paid replay (canonical proof headers or a vanilla
        // X-PAYMENT voucher) must reach the gate/verify path, never a cached
        // page. Disable Joomla's page cache for those requests so the System -
        // Page Cache plugin doesn't serve a stale 200 before we can verify.
        if (ComponentHelper::getParams('com_xenarch')->get('site_token', '') !== ''
            && (PaymentProof::extractPaymentProof() !== null || PaymentProof::extractXPayment() !== null)) {
            $app->allowCache(false);
        }
    }

    /**
     * Bot detection and payment gating — mirrors Xenarch_Gate::maybe_gate_request().
     *
     * Runs on onAfterDispatch (see class docblock): a 404 throws before this
     * fires, so error pages are never gated.
     */
    public function onAfterDispatch(): void
    {
        $app = $this->getApplication();
        if (!$app instanceof SiteApplication) {
            return;
        }

        // No site token configured — plugin not set up yet.
        $params = ComponentHelper::getParams('com_xenarch');
        if ($params->get('site_token', '') === '') {
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

        // Verified canonical payment proof (gate id + on-chain tx hash) → allow.
        $proof = PaymentProof::extractPaymentProof();
        if ($proof && PaymentProof::verify($proof['gate_id'], $proof['tx_hash'])) {
            return;
        }

        // C10 (XEN-457): a third-party x402 agent may pay with a vanilla
        // X-PAYMENT voucher — settle + verify it via the platform and allow on
        // success.
        if ($this->trySettleXPayment($requestUri)) {
            return;
        }

        // FREE pricing rule for this path → allow.
        if ($this->isFreePath($requestUri)) {
            return;
        }

        // Master gate toggle (XEN-364: sourced from the platform, cached 60s).
        $gatingCfg = $this->getGatingConfig();
        if (!$gatingCfg['gating_enabled']) {
            return;
        }

        // Skip static assets — not paywalled content.
        $ext = strtolower(pathinfo(parse_url($requestUri, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
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

        // Allow unknown non-browser traffic through if toggle is off (webhooks etc.).
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

    // ------------------------------------------------------------------
    // Private helpers — ported from WordPress Xenarch_Gate
    // ------------------------------------------------------------------

    private function getRequestHeaders(): array
    {
        $headers = [];
        $map = [
            'HTTP_ACCEPT'                    => 'Accept',
            'HTTP_ACCEPT_LANGUAGE'           => 'Accept-Language',
            'HTTP_ACCEPT_ENCODING'           => 'Accept-Encoding',
            'HTTP_SEC_FETCH_MODE'            => 'Sec-Fetch-Mode',
            'HTTP_SEC_FETCH_DEST'            => 'Sec-Fetch-Dest',
            'HTTP_SEC_FETCH_SITE'            => 'Sec-Fetch-Site',
            'HTTP_SEC_FETCH_USER'            => 'Sec-Fetch-User',
            'HTTP_SEC_CH_UA'                 => 'Sec-Ch-Ua',
            'HTTP_SEC_CH_UA_MOBILE'          => 'Sec-CH-UA-Mobile',
            'HTTP_SEC_CH_UA_PLATFORM'        => 'Sec-CH-UA-Platform',
            'HTTP_UPGRADE_INSECURE_REQUESTS' => 'Upgrade-Insecure-Requests',
            'HTTP_REFERER'                   => 'Referer',
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

    /**
     * Try to settle an inbound vanilla x402 ``X-PAYMENT`` voucher for this
     * request (C10 / XEN-457). Returns true if the platform settled + verified
     * the payment.
     *
     * Unlike the canonical-header path we do NOT fail open: an unsettled
     * voucher is not a paid request, so any error falls through to the gate.
     */
    private function trySettleXPayment(string $requestUri): bool
    {
        $xPayment = PaymentProof::extractXPayment();
        if ($xPayment === null) {
            return false;
        }

        $gate = $this->getOrCreateGate($requestUri, 'x402_x_payment');
        if ($gate === null || empty($gate['gate_id'])) {
            return false;
        }

        try {
            (new ApiClient())->settleX402($gate['gate_id'], $xPayment);
            return true;
        } catch (ApiException $e) {
            return false;
        }
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

        // Social preview bots are always allowed (not configurable).
        if ($detection['traffic_class'] === 'social_preview_fetcher') {
            return false;
        }

        // Per-bot override wins. XEN-393: platform overrides (dashboard /bots)
        // take precedence over the legacy local option, which survives only as
        // a platform-outage fallback. Both arrive together on the gating-config
        // payload / params; no extra request.
        $cfg = $this->getGatingConfig();
        $platformOverrides = isset($cfg['bot_overrides']) && is_array($cfg['bot_overrides']) ? $cfg['bot_overrides'] : [];
        $localOverrides = json_decode((string) ComponentHelper::getParams('com_xenarch')->get('bot_overrides', '{}'), true);
        if (!is_array($localOverrides)) {
            $localOverrides = [];
        }
        $overrides = array_merge($localOverrides, $platformOverrides);
        if (isset($overrides[$signature])) {
            return $overrides[$signature] === 'charge';
        }

        // Fall back to category default.
        $category = $detection['category'] ?? BotDetect::getCategoryForSignature($signature);
        if (empty($category)) {
            return true; // unknown signature → gate
        }

        // XEN-364: category map sourced from the platform; each value is bool
        // (true = gate this category, false = let it pass free).
        $categories = $cfg['gated_categories'];
        if (is_array($categories) && array_key_exists($category, $categories)) {
            return (bool) $categories[$category];
        }

        return true; // gate unknown categories
    }

    /**
     * Fetch the dashboard-managed gating config (XEN-364).
     *
     * Returns ['gating_enabled' => bool, 'gated_categories' => array<string,bool>,
     * 'bot_overrides' => array<string,string>].
     *
     * Resolution order:
     *   1. Per-request memo
     *   2. Joomla cache (60s)
     *   3. Platform GET /v1/sites/me/gating-config (site_token authed)
     *   4. Legacy local-param fallback on API miss — keeps the site enforcing
     *      the publisher's last-known intent during a platform outage rather
     *      than silently flipping behaviour.
     */
    private function getGatingConfig(): array
    {
        if ($this->gatingConfigRequestCache !== null) {
            return $this->gatingConfigRequestCache;
        }

        $cached = Cache::get(self::GATING_CONFIG_CACHE_KEY);
        if (is_array($cached) && isset($cached['gating_enabled'], $cached['gated_categories'])) {
            return $this->gatingConfigRequestCache = $cached;
        }

        try {
            $resp = (new ApiClient())->getGatingConfig();
        } catch (ApiException $e) {
            $resp = null;
        }

        if (is_array($resp)
            && isset($resp['gating_enabled'], $resp['gated_categories'])
            && is_array($resp['gated_categories'])) {
            $botOverrides = [];
            if (isset($resp['bot_overrides']) && is_array($resp['bot_overrides'])) {
                // XEN-393: keep only the platform's allow/charge enum values.
                foreach ($resp['bot_overrides'] as $sig => $val) {
                    if (is_string($val) && ($val === 'allow' || $val === 'charge')) {
                        $botOverrides[(string) $sig] = $val;
                    }
                }
            }
            $cfg = [
                'gating_enabled'   => (bool) $resp['gating_enabled'],
                'gated_categories' => array_map('boolval', $resp['gated_categories']),
                'bot_overrides'    => $botOverrides,
            ];
            Cache::set(self::GATING_CONFIG_CACHE_KEY, $cfg, self::GATING_CONFIG_TTL);
            return $this->gatingConfigRequestCache = $cfg;
        }

        // API miss — read the legacy local params so behaviour reflects *some*
        // publisher intent rather than a silent flip.
        $params = ComponentHelper::getParams('com_xenarch');
        $legacyCats = json_decode((string) $params->get('bot_categories', '{}'), true);
        $normalizedCats = [];
        if (is_array($legacyCats)) {
            foreach ($legacyCats as $key => $val) {
                // Legacy values were 'charge'/'allow'; new shape is bool.
                $normalizedCats[$key] = is_bool($val) ? $val : ($val === 'charge');
            }
        }

        $fallback = [
            'gating_enabled'   => $params->get('gate_enabled', '1') === '1',
            'gated_categories' => $normalizedCats,
            'bot_overrides'    => [], // local override option still applies via the caller's merge
        ];
        return $this->gatingConfigRequestCache = $fallback;
    }

    private function isFreePath(string $requestUri): bool
    {
        $matched = $this->matchPricingRule($requestUri);
        return $matched !== null && (string) $matched === '0';
    }

    /**
     * Match a request path against the legacy local pricing rules (a fast-path
     * FREE check). Pricing is platform-canonical post-pivot and this local
     * option is no longer written, so on a fresh install this is a no-op —
     * exactly mirroring the WordPress gate.
     */
    private function matchPricingRule(string $requestUri): ?string
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $rules = json_decode((string) ComponentHelper::getParams('com_xenarch')->get('pricing_rules', '[]'), true);

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

    /**
     * Get or create a gate for the given path, cached 30 min (mirrors WP).
     */
    private function getOrCreateGate(string $requestUri, string $detectionMethod = 'ua_match'): ?array
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $cacheKey = 'xenarch_gate_' . md5($path);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $gate = (new ApiClient())->createGate($path, $detectionMethod);
        } catch (ApiException $e) {
            return null;
        }

        Cache::set($cacheKey, $gate, self::GATE_CACHE_TTL);
        return $gate;
    }

    private function renderBrowserChallenge(string $requestUri, string $userAgent, array $detection): void
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $cookieValue = BrowserProof::issueCookieValue($userAgent);

        // Emit headers raw — we exit immediately, so Joomla's deferred
        // response headers would never flush.
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Xenarch-Bot: ' . $detection['method']);
        header('X-Xenarch-Decision: challenge');
        echo GateResponse::buildChallengeHtml($path, $cookieValue);
        $this->getApplication()->close();
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
        } elseif (!isset($gate['xenarch'])) {
            $gate['xenarch'] = true;
        }

        $gate = $this->enrichGatePayload($gate);

        http_response_code(402);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Xenarch-Bot: ' . $detection['method']);
        header('X-Xenarch-Decision: block');
        foreach ($this->getDiscoveryHeaders() as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($gate, JSON_UNESCAPED_SLASHES);
        $this->getApplication()->close();
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

    /**
     * Report a detected bot to the platform (XEN-394).
     *
     * Fire-and-forget POST — the platform is the canonical store for detection
     * telemetry (dashboard /bots cross-site activity). No local table, no cron,
     * no sync bookkeeping; a dropped event reads as a slightly low count, never
     * a duplicate.
     */
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
            return; // no useful signature for header-scored bots
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

        try {
            (new ApiClient())->postBotDetections([[
                'signature' => $signature,
                'category'  => (string) $category,
                'company'   => (string) $company,
                // occurred_at omitted — platform fills NOW().
            ]]);
        } catch (\Throwable $e) {
            // best-effort telemetry; never block the response.
        }
    }
}

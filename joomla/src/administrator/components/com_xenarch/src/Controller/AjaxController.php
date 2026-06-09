<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 *
 * Admin-side AJAX controller for the React dashboard — the Joomla analogue
 * of the WordPress plugin's class-xenarch-rest.php.
 *
 * Post-XEN-481 the plugin is a thin window into the platform. Almost nothing
 * the merchant cares about lives in the Joomla DB — payout wallet, pricing,
 * gating, earnings, transactions all live on the backend and are managed in
 * dash.xenarch.dev. This controller does two kinds of thing:
 *
 *   1. ``settings`` — return the plugin-local snapshot the React app needs to
 *      decide which screen to render (am I paired with a site_token yet?).
 *   2. ``claim-exchange`` / ``disconnect`` — manage the local site_token.
 *   3. thin-window proxies (``site``, ``gating``, ``pricing``, ``stats``,
 *      ``transactions``, ``category-breakdown``, ``wallets``,
 *      ``payout-wallet``) — forward to the platform's site_token-authed
 *      /v1/sites/me/* surface so the browser stays same-origin.
 */

namespace Xenarch\Component\Xenarch\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Xenarch\Component\Xenarch\Administrator\Helper\XenarchHelper;
use Xenarch\Plugin\System\Xenarch\ApiClient;
use Xenarch\Plugin\System\Xenarch\ApiException;
use Xenarch\Plugin\System\Xenarch\BotDetect;
use Xenarch\Plugin\System\Xenarch\Cache;

class AjaxController extends BaseController
{
    /** Cache key the system-plugin gate uses for the platform gating config. */
    private const GATING_CONFIG_CACHE_KEY = 'xenarch_gating_config_cache';

    public function execute($task)
    {
        try {
            $endpoint = str_replace('ajax.', '', (string) $task);
            $this->checkAuth();

            $method = strtoupper($this->input->getString('method', $this->input->getMethod()));
            $isWrite = in_array($method, ['POST', 'PUT'], true);

            switch ($endpoint) {
                case 'settings':
                    $this->getSettings();
                    break;
                case 'claimexchange':
                    $this->claimExchange();
                    break;
                case 'disconnect':
                    $this->disconnect();
                    break;
                case 'site':
                    $this->forward(fn (ApiClient $api) => $api->getMySite());
                    break;
                case 'gating':
                    $isWrite ? $this->putGating() : $this->methodNotAllowed();
                    break;
                case 'pricing':
                    $isWrite ? $this->putPricing() : $this->methodNotAllowed();
                    break;
                case 'stats':
                    $this->forward(fn (ApiClient $api) => $api->getMySiteStats());
                    break;
                case 'transactions':
                    $this->getTransactions();
                    break;
                case 'categorybreakdown':
                    $this->forward(fn (ApiClient $api) => $api->getMySiteCategoryBreakdown());
                    break;
                case 'wallets':
                    $this->forward(fn (ApiClient $api) => $api->getMySiteWallets());
                    break;
                case 'payoutwallet':
                    $this->putPayoutWallet();
                    break;
                default:
                    $this->sendJson(['error' => 'unknown_endpoint'], 404);
            }
        } catch (\Throwable $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            $this->sendJson(['error' => $e->getMessage()], $code);
        }

        return $this;
    }

    // ── Auth ──────────────────────────────────────────────

    private function checkAuth(): void
    {
        if (!Session::checkToken('request')) {
            throw new \RuntimeException('Invalid CSRF token', 403);
        }

        $user = $this->app->getIdentity();
        if (!$user || $user->guest || !$user->authorise('core.manage', 'com_xenarch')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    // ── Helpers ──────────────────────────────────────────

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?: [];
    }

    private function sendJson(array $data, int $code = 200): void
    {
        // Emit raw — we exit via close() immediately, so Joomla's deferred
        // response headers (set via $app->setHeader) would never flush.
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }

    private function methodNotAllowed(): void
    {
        $this->sendJson(['error' => 'method_not_allowed'], 405);
    }

    /**
     * Forward a platform call to the React caller, preserving useful platform
     * status (400 / 401 / 404 / 502) — the Joomla analogue of the WP REST
     * forward() wrapper.
     */
    private function forward(callable $call): void
    {
        try {
            $result = $call(new ApiClient());
        } catch (ApiException $e) {
            $status = $e->status;
            if ($status < 400 || $status > 599) {
                $status = 502;
            }
            $this->sendJson(['error' => 'xenarch_api_error', 'detail' => $e->getMessage()], $status);
            return;
        }
        $this->sendJson($result);
    }

    /**
     * Plugin-local snapshot — only what the React app needs to render the
     * onboarding-vs-main decision and the read-only status fields. The
     * platform owns everything else; the admin tabs fetch their own data live.
     */
    private function snapshot(): array
    {
        $params = XenarchHelper::getAllParams();
        $siteToken = (string) ($params['site_token'] ?? '');
        $siteUrl = XenarchHelper::getSiteUrl();

        $botSignatureCount = count(BotDetect::getSignatures()) + count(BotDetect::getFetcherSignatures());

        return [
            'site_id'             => (string) ($params['site_id'] ?? ''),
            'site_token'          => $siteToken,
            'domain'              => XenarchHelper::getSiteDomain(),
            'has_site'            => $siteToken !== '',
            'bot_signature_count' => $botSignatureCount,
            'pay_json_url'        => $siteUrl . '/.well-known/pay.json',
            'xenarch_md_url'      => $siteUrl . '/.well-known/xenarch.md',
        ];
    }

    // ── Plugin-local ─────────────────────────────────────

    private function getSettings(): void
    {
        $this->sendJson($this->snapshot());
    }

    /**
     * Swap a one-time claim_token (issued by dash.xenarch.dev/sites/claim) for
     * the long-lived site_token, then persist it locally and unlock the UI.
     */
    private function claimExchange(): void
    {
        $body = $this->getJsonBody();
        $claimToken = isset($body['claim_token']) ? trim((string) $body['claim_token']) : '';

        if ($claimToken === '' || strlen($claimToken) > 256 || !preg_match('/^[\x21-\x7e]+$/', $claimToken)) {
            $this->sendJson(['error' => 'invalid_claim_token'], 400);
            return;
        }

        try {
            $result = (new ApiClient())->exchangeClaimToken($claimToken);
        } catch (ApiException $e) {
            // 404 = expired / replayed claim; 400 = malformed.
            $http = in_array($e->status, [400, 404], true) ? $e->status : 502;
            $this->sendJson(['error' => 'claim_exchange_failed', 'detail' => $e->getMessage()], $http);
            return;
        }

        if (empty($result['site_token']) || empty($result['site_id'])) {
            $this->sendJson(['error' => 'claim_exchange_malformed'], 502);
            return;
        }

        XenarchHelper::setParams([
            'site_token' => (string) $result['site_token'],
            'site_id'    => (string) $result['site_id'],
        ]);

        // Bust the gating-config cache so the gate picks up the dashboard state
        // without waiting out the 60s TTL.
        Cache::remove(self::GATING_CONFIG_CACHE_KEY);

        $this->sendJson($this->snapshot());
    }

    /**
     * Disconnect the plugin from the platform. Wipes the site_token but leaves
     * the browser-proof secret in place so reconnecting under the same identity
     * doesn't reset learned state.
     */
    private function disconnect(): void
    {
        XenarchHelper::setParams([
            'site_token' => '',
            'site_id'    => '',
        ]);
        Cache::remove(self::GATING_CONFIG_CACHE_KEY);
        $this->sendJson($this->snapshot());
    }

    // ── Thin-window proxies ──────────────────────────────

    private function putGating(): void
    {
        $body = $this->getJsonBody();
        $gatingEnabled = isset($body['gating_enabled']) ? (bool) $body['gating_enabled'] : true;
        $gatedCategories = isset($body['gated_categories']) && is_array($body['gated_categories']) ? $body['gated_categories'] : [];
        $usePublisherDefaults = isset($body['use_publisher_defaults']) ? (bool) $body['use_publisher_defaults'] : false;

        $this->forward(fn (ApiClient $api) => $api->putMySiteGating($gatingEnabled, $gatedCategories, $usePublisherDefaults));
    }

    private function putPricing(): void
    {
        $body = $this->getJsonBody();
        // A pricing edit must state the default explicitly — the platform is the
        // single source of truth; never fabricate one from a hardcoded literal.
        if (!isset($body['default_price_usd'])) {
            $this->sendJson(['error' => 'xenarch_missing_price', 'detail' => 'default_price_usd is required'], 400);
            return;
        }
        $defaultPrice = (float) $body['default_price_usd'];
        $rules = isset($body['rules']) && is_array($body['rules']) ? $body['rules'] : [];
        $scope = isset($body['default_billing_scope']) ? (string) $body['default_billing_scope'] : 'page';

        $this->forward(fn (ApiClient $api) => $api->putMySitePricing($defaultPrice, $rules, $scope));
    }

    private function getTransactions(): void
    {
        $params = [
            'period'   => $this->input->getString('period', 'all'),
            'status'   => $this->input->getString('status', 'all'),
            'page'     => max(1, $this->input->getInt('page', 1)),
            'per_page' => min(100, max(1, $this->input->getInt('per_page', 25))),
        ];
        $this->forward(fn (ApiClient $api) => $api->getMySiteTransactions($params));
    }

    private function putPayoutWallet(): void
    {
        $body = $this->getJsonBody();
        $wallet = isset($body['wallet']) ? trim((string) $body['wallet']) : '';
        if (!preg_match('/^0x[0-9a-fA-F]{40}$/', $wallet)) {
            $this->sendJson(['error' => 'invalid_wallet'], 400);
            return;
        }
        $this->forward(fn (ApiClient $api) => $api->putMySitePayoutWallet($wallet));
    }
}

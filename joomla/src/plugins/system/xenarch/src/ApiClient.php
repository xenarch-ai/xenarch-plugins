<?php
/**
 * Xenarch platform API client — Joomla port.
 *
 * 1:1 mirror of the WordPress plugin's Xenarch_Api (post-XEN-380
 * thin-window rewrite). The plugin is a thin window into the platform —
 * this client does only three things:
 *
 *   1. talk to the gate hot-path (create gate, verify on-chain payment,
 *      settle inbound x402, read dashboard-managed gating config),
 *   2. complete the claim handshake that boots a fresh install
 *      (exchangeClaimToken),
 *   3. read/write the merchant-owned site state through the
 *      site_token-authed /v1/sites/me/* surface (pricing, gating,
 *      payout wallet, stats, transactions, category breakdown).
 *
 * All Bearer-API-key endpoints are gone — pricing, payouts, wallet
 * linking, transactions, balances are managed on the platform and read
 * through site_token, never an api_key. There is no publisher
 * registration and no offramp/cash-out here; that lives in the dashboard.
 *
 * Auth is the per-site ``X-Site-Token`` header, sourced from the
 * com_xenarch component params.
 *
 * Errors: successful calls return an array. Failures throw ApiException
 * carrying the HTTP status (0 for transport failures) so callers can
 * fail closed on 4xx / fail open on 5xx, exactly like the WP_Error
 * ``status`` data in the WordPress client.
 *
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\System\Xenarch;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Http\HttpFactory;

class ApiClient
{
    private string $baseUrl;

    public function __construct(string $baseUrl = 'https://api.xenarch.dev')
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Public-config endpoint. The plugin doesn't actively use the values
     * it returns today, but the call is harmless and cheap — kept so
     * status checks can ping it. No auth.
     */
    public function getConfig(): array
    {
        return $this->get('/v1/config');
    }

    /**
     * Swap a one-time claim_token (issued by dash.xenarch.dev/sites/claim)
     * for the long-lived site_token. Server-to-server only — the
     * claim_token never lives anywhere except its single trip through the
     * redirect URL and this request.
     *
     * @return array {site_id, site_token, domain, integration_type}
     */
    public function exchangeClaimToken(string $claimToken): array
    {
        return $this->post('/v1/site-claims/' . urlencode($claimToken) . '/exchange', []);
    }

    /**
     * Verify an on-chain payment for a gate. Stateless — the platform
     * re-derives everything from the tx hash on each call.
     */
    public function verifyPayment(string $gateId, string $txHash): array
    {
        return $this->post(
            '/v1/gates/' . urlencode($gateId) . '/verify',
            ['tx_hash' => $txHash],
            $this->siteTokenHeaders()
        );
    }

    /**
     * Settle + verify an inbound vanilla x402 ``X-PAYMENT`` voucher
     * (C10 / XEN-457). The platform routes settlement through a
     * facilitator (Xenarch never broadcasts) and verifies the on-chain
     * Transfer.
     */
    public function settleX402(string $gateId, string $xPayment, ?string $facilitator = null): array
    {
        $body = ['x_payment' => $xPayment];
        if ($facilitator !== null) {
            $body['facilitator'] = $facilitator;
        }
        return $this->post(
            '/v1/gates/' . urlencode($gateId) . '/settle-x402',
            $body,
            $this->siteTokenHeaders()
        );
    }

    /**
     * Read the dashboard-managed gating state for this site. Returns
     * {gating_enabled, gated_categories, bot_overrides, ...}. Cached by
     * the caller (see the gate plugin).
     */
    public function getGatingConfig(): array
    {
        return $this->get('/v1/sites/me/gating-config', $this->siteTokenHeaders());
    }

    /**
     * Create a gate on the platform for a freshly detected bot request.
     * Returns the full x402 envelope (with accepts + facilitators) which
     * the plugin echoes back as the 402 body. 402 is treated as success.
     */
    public function createGate(string $url, string $detectionMethod = 'ua_match'): array
    {
        return $this->post(
            '/v1/gates',
            ['url' => $url, 'detection_method' => $detectionMethod],
            $this->siteTokenHeaders(),
            'POST',
            true // allow 402 as success
        );
    }

    /**
     * Push a batch of bot-detection rows to the platform (XEN-394) so the
     * dashboard /bots cross-site activity panel can aggregate them.
     *
     * Fire-and-forget: called inline on every bot detection, so it must
     * not add meaningful latency to bot page loads and we don't care about
     * the response (the platform is canonical; failures show up as missing
     * detections, not duplicates). PHP can't truly detach the request the
     * way WP's non-blocking wp_remote_post does, so we use a tight timeout
     * and swallow every error.
     *
     * @param array $detections list of {signature, category, company,
     *                          first_seen, last_seen, hit_count}.
     */
    public function postBotDetections(array $detections): void
    {
        $siteToken = $this->siteToken();
        if ($siteToken === '' || empty($detections)) {
            return;
        }

        $url     = $this->baseUrl . '/v1/sites/me/bot-detections';
        $headers = [
            'Content-Type' => 'application/json',
            'X-Site-Token' => $siteToken,
        ];
        $body = json_encode(['detections' => array_values($detections)], JSON_UNESCAPED_SLASHES);

        try {
            // Tight timeout — open the connection, don't wait on the body.
            HttpFactory::getHttp()->post($url, $body, $headers, 2);
        } catch (\Throwable $e) {
            // best-effort telemetry; ignore.
        }
    }

    // ------------------------------------------------------------------
    // XEN-380 / XEN-383 — thin-window mirror of /v1/sites/me/*
    // All X-Site-Token authed.
    // ------------------------------------------------------------------

    /** Full site detail (mirror of dashboard /sites/[id]). */
    public function getMySite(): array
    {
        return $this->get('/v1/sites/me', $this->siteTokenHeaders());
    }

    /** PUT gating — full replace of gating_enabled + gated_categories + use_publisher_defaults. */
    public function putMySiteGating(bool $gatingEnabled, array $gatedCategories, bool $usePublisherDefaults): array
    {
        return $this->post(
            '/v1/sites/me/gating',
            [
                'gating_enabled'         => $gatingEnabled,
                'gated_categories'       => (object) $gatedCategories,
                'use_publisher_defaults' => $usePublisherDefaults,
            ],
            $this->siteTokenHeaders(),
            'PUT'
        );
    }

    /** PUT pricing — default + per-path rules. */
    public function putMySitePricing(float $defaultPriceUsd, array $rules, string $defaultBillingScope = 'page'): array
    {
        return $this->post(
            '/v1/sites/me/pricing',
            [
                'default_price_usd'     => $defaultPriceUsd,
                'default_billing_scope' => $defaultBillingScope,
                'rules'                 => array_values($rules),
            ],
            $this->siteTokenHeaders(),
            'PUT'
        );
    }

    /** Today / month / all-time stats. */
    public function getMySiteStats(): array
    {
        return $this->get('/v1/sites/me/stats', $this->siteTokenHeaders());
    }

    /** Paginated transactions feed. */
    public function getMySiteTransactions(array $params = []): array
    {
        $qs = http_build_query($params);
        return $this->get('/v1/sites/me/transactions' . ($qs ? '?' . $qs : ''), $this->siteTokenHeaders());
    }

    /** Earnings grouped by detected bot category. */
    public function getMySiteCategoryBreakdown(): array
    {
        return $this->get('/v1/sites/me/category-breakdown', $this->siteTokenHeaders());
    }

    /**
     * XEN-435 P4: the merchant identity's linked wallets, so the admin can
     * pick which one receives gate revenue. Returns
     * {wallets:[{address, eligible_at, eligible, is_default}]}.
     */
    public function getMySiteWallets(): array
    {
        return $this->get('/v1/sites/me/wallets', $this->siteTokenHeaders());
    }

    /**
     * XEN-435 P4: set the gate's receiving wallet (the identity default).
     * The platform only accepts an already-eligible linked wallet.
     */
    public function putMySitePayoutWallet(string $wallet): array
    {
        return $this->post(
            '/v1/sites/me/payout-wallet',
            ['wallet' => $wallet],
            $this->siteTokenHeaders(),
            'PUT'
        );
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function siteToken(): string
    {
        return (string) ComponentHelper::getParams('com_xenarch')->get('site_token', '');
    }

    /**
     * @throws ApiException when no site_token is configured — callers
     *                      treat this the same as a transport failure.
     */
    private function siteTokenHeaders(): array
    {
        $siteToken = $this->siteToken();
        if ($siteToken === '') {
            throw new ApiException(0, 'site token not configured');
        }
        return ['X-Site-Token' => $siteToken];
    }

    /**
     * @throws ApiException on any non-success response or transport error.
     */
    private function post(string $endpoint, array $body = [], array $headers = [], string $method = 'POST', bool $allow402 = false): array
    {
        $url = $this->baseUrl . $endpoint;
        $headers['Content-Type'] = 'application/json';
        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);

        try {
            $http = HttpFactory::getHttp();
            $response = $method === 'PUT'
                ? $http->put($url, $jsonBody, $headers, 30)
                : $http->post($url, $jsonBody, $headers, 30);
        } catch (\Throwable $e) {
            throw new ApiException(0, $e->getMessage());
        }

        return $this->handleResponse($response, $allow402);
    }

    /**
     * @throws ApiException on any non-success response or transport error.
     */
    private function get(string $endpoint, array $headers = []): array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $response = HttpFactory::getHttp()->get($url, $headers, 30);
        } catch (\Throwable $e) {
            throw new ApiException(0, $e->getMessage());
        }

        return $this->handleResponse($response);
    }

    /**
     * @throws ApiException on any non-success response.
     */
    private function handleResponse(object $response, bool $allow402 = false): array
    {
        $code = (int) ($response->code ?? 0);
        $body = $response->body ?? '';
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            return is_array($data) ? $data : [];
        }

        if ($allow402 && $code === 402 && is_array($data)) {
            return $data;
        }

        $message = '';
        if (is_array($data)) {
            if (isset($data['message'])) {
                $message = (string) $data['message'];
            } elseif (isset($data['detail'])) {
                $message = is_array($data['detail']) ? json_encode($data['detail']) : (string) $data['detail'];
            }
        }

        throw new ApiException($code, $message);
    }
}

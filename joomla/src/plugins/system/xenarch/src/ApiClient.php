<?php
/**
 * Xenarch API client — Joomla port.
 *
 * Wraps all communication with the xenarch.dev REST API using
 * Joomla's HttpFactory.
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

    public function getConfig(): ?array
    {
        return $this->get('/v1/config');
    }

    public function register(string $email, string $password): ?array
    {
        return $this->post('/v1/publishers', ['email' => $email, 'password' => $password]);
    }

    public function addSite(string $domain): ?array
    {
        return $this->post('/v1/sites', ['domain' => $domain], $this->authHeaders());
    }

    /**
     * Verify an on-chain payment against a gate.
     *
     * Posts the tx hash to the platform, which re-checks the transaction
     * on Base and confirms it satisfies the gate's price + recipient.
     * No JWT, no cached session — verification is stateless per call.
     */
    public function verifyPayment(string $gateId, string $txHash): ?array
    {
        $params = ComponentHelper::getParams('com_xenarch');
        $siteToken = $params->get('site_token', '');

        return $this->post(
            '/v1/gates/' . urlencode($gateId) . '/verify',
            ['tx_hash' => $txHash],
            ['X-Site-Token' => $siteToken]
        );
    }

    public function listSites(): ?array
    {
        return $this->get('/v1/sites', $this->authHeaders());
    }

    public function updatePricing(string $siteId, float $defaultPrice, array $rules = []): ?array
    {
        $body = ['default_price_usd' => $defaultPrice];
        if (!empty($rules)) {
            $body['rules'] = $rules;
        }
        return $this->post('/v1/sites/' . urlencode($siteId) . '/pricing', $body, $this->authHeaders(), 'PUT');
    }

    public function updatePayout(string $wallet, string $network = ''): ?array
    {
        if (empty($network)) {
            $params = ComponentHelper::getParams('com_xenarch');
            $network = $params->get('wallet_network', 'base');
        }
        return $this->post('/v1/publishers/me/payout', ['wallet' => $wallet, 'network' => $network], $this->authHeaders(), 'PUT');
    }

    public function getProfile(): ?array
    {
        return $this->get('/v1/publishers/me', $this->authHeaders());
    }

    public function createGate(string $url, string $detectionMethod = 'ua_match'): ?array
    {
        $params = ComponentHelper::getParams('com_xenarch');
        $siteToken = $params->get('site_token', '');

        return $this->post(
            '/v1/gates',
            ['url' => $url, 'detection_method' => $detectionMethod],
            ['X-Site-Token' => $siteToken],
            'POST',
            true
        );
    }

    public function getStats(string $siteId): ?array
    {
        return $this->get('/v1/sites/' . urlencode($siteId) . '/stats', $this->authHeaders());
    }

    public function getBalance(string $siteId): ?array
    {
        return $this->get('/v1/sites/' . urlencode($siteId) . '/balance', $this->authHeaders());
    }

    public function getCategoryBreakdown(string $siteId): ?array
    {
        return $this->get('/v1/sites/' . urlencode($siteId) . '/category-breakdown', $this->authHeaders());
    }

    public function getTransactions(string $siteId, array $params = []): ?array
    {
        $query = http_build_query($params);
        $endpoint = '/v1/sites/' . urlencode($siteId) . '/transactions';
        if (!empty($query)) {
            $endpoint .= '?' . $query;
        }
        return $this->get($endpoint, $this->authHeaders());
    }

    public function getSellConfig(): ?array
    {
        return $this->get('/v1/offramp/sell-config', $this->authHeaders());
    }

    public function getSellOptions(string $country): ?array
    {
        return $this->get('/v1/offramp/sell-options?country=' . urlencode($country), $this->authHeaders());
    }

    public function createSellQuote(string $siteId, string $amountUsd, string $country, string $paymentMethod = 'FIAT_WALLET'): ?array
    {
        return $this->post('/v1/offramp/sell-quote', [
            'site_id'        => $siteId,
            'amount_usd'     => $amountUsd,
            'country'        => $country,
            'payment_method' => $paymentMethod,
        ], $this->authHeaders());
    }

    public function recordCashOut(string $siteId, string $amountUsd): ?array
    {
        return $this->post('/v1/sites/' . urlencode($siteId) . '/cash-outs', ['amount_usd' => $amountUsd], $this->authHeaders());
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function authHeaders(): array
    {
        $params = ComponentHelper::getParams('com_xenarch');
        $apiKey = $params->get('api_key', '');

        return ['Authorization' => 'Bearer ' . $apiKey];
    }

    private function post(string $endpoint, array $body = [], array $headers = [], string $method = 'POST', bool $allow402 = false): ?array
    {
        $url = $this->baseUrl . $endpoint;
        $headers['Content-Type'] = 'application/json';

        try {
            $http = HttpFactory::getHttp();
            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);

            if ($method === 'PUT') {
                $response = $http->put($url, $jsonBody, $headers, 30);
            } else {
                $response = $http->post($url, $jsonBody, $headers, 30);
            }

            return $this->handleResponse($response, $allow402);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function get(string $endpoint, array $headers = []): ?array
    {
        $url = $this->baseUrl . $endpoint;

        try {
            $http = HttpFactory::getHttp();
            $response = $http->get($url, $headers, 30);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function handleResponse(object $response, bool $allow402 = false): ?array
    {
        $code = $response->code;
        $body = $response->body ?? '';
        $data = json_decode($body, true);

        if ($code >= 200 && $code < 300) {
            return is_array($data) ? $data : [];
        }

        if ($allow402 && $code === 402 && is_array($data)) {
            return $data;
        }

        return null;
    }
}

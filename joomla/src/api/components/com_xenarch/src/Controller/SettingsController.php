<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Component\Xenarch\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Component\ComponentHelper;
use Xenarch\Component\Xenarch\Administrator\Helper\XenarchHelper;
use Xenarch\Plugin\System\Xenarch\ApiClient;
use Xenarch\Plugin\System\Xenarch\BotDetect;

class SettingsController extends ApiController
{
    protected $contentType = 'settings';

    /**
     * GET /settings
     */
    public function displayList(): void
    {
        $this->checkAdmin();
        $this->sendJson($this->getAllSettings());
    }

    /**
     * POST /settings
     */
    public function add(): void
    {
        $this->checkAdmin();
        $data = json_decode($this->input->json->getRaw(), true) ?: [];

        $allowed = [
            'xenarch_default_price' => 'default_price',
            'xenarch_payout_wallet' => 'payout_wallet',
            'xenarch_gate_unknown_traffic' => 'gate_unknown_traffic',
            'xenarch_gate_enabled' => 'gate_enabled',
            'xenarch_wallet_type' => 'wallet_type',
            'xenarch_wallet_network' => 'wallet_network',
        ];

        $updates = [];
        foreach ($allowed as $requestKey => $paramKey) {
            if (isset($data[$requestKey])) {
                $updates[$paramKey] = filter_var($data[$requestKey], FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }

        if (!empty($updates)) {
            XenarchHelper::setParams($updates);
        }

        // Sync pricing to platform.
        $params = XenarchHelper::getAllParams();
        $siteId = $params['site_id'] ?? '';
        if (!empty($siteId) && isset($data['xenarch_default_price'])) {
            $rules = json_decode($params['pricing_rules'] ?? '[]', true);
            $apiRules = [];
            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    if (!empty($rule['path_contains']) && isset($rule['price_usd'])) {
                        $apiRules[] = ['path' => '*' . $rule['path_contains'] . '*', 'price_usd' => (float) $rule['price_usd']];
                    }
                }
            }
            $api = new ApiClient();
            $api->updatePricing($siteId, (float) ($params['default_price'] ?? '0.003'), $apiRules);
        }

        // Sync payout wallet.
        if (isset($data['xenarch_payout_wallet']) && !empty($data['xenarch_payout_wallet'])) {
            $api = new ApiClient();
            $api->updatePayout($data['xenarch_payout_wallet']);
        }

        $this->sendJson($this->getAllSettings());
    }

    private function getAllSettings(): array
    {
        $params = XenarchHelper::getAllParams();
        $categories = json_decode($params['bot_categories'] ?? '{}', true);
        $overrides = json_decode($params['bot_overrides'] ?? '{}', true);
        $siteUrl = XenarchHelper::getSiteUrl();

        $botSignatureCount = 0;
        if (class_exists('\\Xenarch\\Plugin\\System\\Xenarch\\BotDetect')) {
            $botSignatureCount = count(BotDetect::getSignatures()) + count(BotDetect::getFetcherSignatures());
        }

        return [
            'api_key'             => !empty($params['api_key'] ?? ''),
            'site_id'             => $params['site_id'] ?? '',
            'site_token'          => $params['site_token'] ?? '',
            'default_price'       => $params['default_price'] ?? '0.003',
            'payout_wallet'       => $params['payout_wallet'] ?? '',
            'gate_unknown_traffic' => $params['gate_unknown_traffic'] ?? '1',
            'gate_enabled'        => $params['gate_enabled'] ?? '1',
            'bot_categories'      => is_array($categories) ? $categories : [],
            'bot_overrides'       => is_array($overrides) ? $overrides : [],
            'wallet_type'         => $params['wallet_type'] ?? '',
            'wallet_network'      => $params['wallet_network'] ?? 'base',
            'domain'              => XenarchHelper::getSiteDomain(),
            'has_wallet'          => !empty($params['payout_wallet'] ?? ''),
            'has_site'            => !empty($params['site_id'] ?? '') && !empty($params['site_token'] ?? ''),
            'bot_signature_count' => $botSignatureCount,
            'pay_json_url'        => $siteUrl . '/.well-known/pay.json',
            'xenarch_md_url'      => $siteUrl . '/.well-known/xenarch.md',
        ];
    }

    private function checkAdmin(): void
    {
        $user = $this->app->getIdentity();
        if (!$user || !$user->authorise('core.manage', 'com_xenarch')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    private function sendJson(array $data, int $code = 200): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }
}

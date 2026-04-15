<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Component\Xenarch\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Xenarch\Component\Xenarch\Administrator\Helper\XenarchHelper;
use Xenarch\Plugin\System\Xenarch\ApiClient;

class PricingrulesController extends ApiController
{
    protected $contentType = 'pricingrules';

    public function displayList(): void
    {
        $this->checkAdmin();
        $rules = XenarchHelper::getJsonParam('pricing_rules', '[]');
        $this->sendJson(['rules' => $rules]);
    }

    public function edit(): void
    {
        $this->checkAdmin();
        $data = json_decode($this->input->json->getRaw(), true) ?: [];
        $rules = $data['rules'] ?? [];

        $cleanRules = [];
        foreach ($rules as $rule) {
            if (isset($rule['path_contains']) && isset($rule['price_usd'])) {
                $cleanRules[] = [
                    'path_contains' => filter_var($rule['path_contains'], FILTER_SANITIZE_SPECIAL_CHARS),
                    'price_usd'     => filter_var($rule['price_usd'], FILTER_SANITIZE_SPECIAL_CHARS),
                    'billing_scope' => isset($rule['billing_scope']) && $rule['billing_scope'] === 'path' ? 'path' : 'page',
                ];
            }
        }

        XenarchHelper::setParam('pricing_rules', json_encode($cleanRules));

        // Sync to platform.
        $params = XenarchHelper::getAllParams();
        $siteId = $params['site_id'] ?? '';
        if (!empty($siteId)) {
            $apiRules = [];
            foreach ($cleanRules as $rule) {
                $apiRules[] = [
                    'path'          => '*' . $rule['path_contains'] . '*',
                    'price_usd'     => (float) $rule['price_usd'],
                    'billing_scope' => $rule['billing_scope'],
                ];
            }
            $api = new ApiClient();
            $api->updatePricing($siteId, (float) ($params['default_price'] ?? '0.003'), $apiRules);
        }

        $this->sendJson(['rules' => $cleanRules]);
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

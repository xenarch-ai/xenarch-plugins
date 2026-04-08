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

class BalanceController extends ApiController
{
    protected $contentType = 'balance';

    public function displayList(): void
    {
        $this->checkAdmin();
        $siteId = XenarchHelper::getParam('site_id');

        if (empty($siteId)) {
            $this->sendJson(['balance_usd' => '0.00']);
            return;
        }

        $api = new ApiClient();
        $result = $api->getBalance($siteId);

        $this->sendJson([
            'balance_usd' => ($result !== null && isset($result['balance_usd'])) ? $result['balance_usd'] : '0.00',
        ]);
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

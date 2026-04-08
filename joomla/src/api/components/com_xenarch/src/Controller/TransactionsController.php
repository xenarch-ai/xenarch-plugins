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

class TransactionsController extends ApiController
{
    protected $contentType = 'transactions';

    public function displayList(): void
    {
        $this->checkAdmin();
        $siteId = XenarchHelper::getParam('site_id');

        if (empty($siteId)) {
            $this->sendJson(['error' => 'Site not configured.'], 400);
            return;
        }

        $params = [];
        $period = $this->input->getString('period', '');
        if (!empty($period)) $params['period'] = $period;

        $page = $this->input->getInt('page', 0);
        if ($page > 0) $params['page'] = $page;

        $perPage = $this->input->getInt('per_page', 0);
        if ($perPage > 0) $params['per_page'] = min($perPage, 100);

        $status = $this->input->getString('status', '');
        if (!empty($status) && in_array($status, ['paid', 'blocked', 'withdraw', 'all'], true)) {
            $params['status'] = $status;
        }

        $api = new ApiClient();
        $result = $api->getTransactions($siteId, $params);

        if ($result === null) {
            $this->sendJson(['error' => 'Failed to fetch transactions.'], 502);
            return;
        }

        $this->sendJson($result);
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

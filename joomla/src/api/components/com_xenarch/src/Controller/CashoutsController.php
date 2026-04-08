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

class CashoutsController extends ApiController
{
    protected $contentType = 'cashouts';

    public function add(): void
    {
        $this->checkAdmin();
        $data = json_decode($this->input->json->getRaw(), true) ?: [];
        $amountUsd = filter_var($data['amount_usd'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);

        if (empty($amountUsd)) {
            $this->sendJson(['error' => 'Amount is required.'], 400);
            return;
        }

        $siteId = XenarchHelper::getParam('site_id');
        if (empty($siteId)) {
            $this->sendJson(['error' => 'Site not configured.'], 400);
            return;
        }

        $api = new ApiClient();
        $result = $api->recordCashOut($siteId, $amountUsd);

        if ($result === null) {
            $this->sendJson(['error' => 'Failed to record cash out.'], 502);
            return;
        }

        $this->sendJson($result, 201);
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

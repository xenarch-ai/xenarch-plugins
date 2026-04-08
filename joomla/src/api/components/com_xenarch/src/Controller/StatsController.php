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

class StatsController extends ApiController
{
    protected $contentType = 'stats';

    public function displayList(): void
    {
        $this->checkAdmin();
        $siteId = XenarchHelper::getParam('site_id');

        if (empty($siteId)) {
            $this->sendJson(['error' => 'Site not configured.'], 400);
            return;
        }

        $api = new ApiClient();
        $result = $api->getStats($siteId);

        if ($result === null) {
            $this->sendJson(['error' => 'Failed to fetch stats.'], 502);
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

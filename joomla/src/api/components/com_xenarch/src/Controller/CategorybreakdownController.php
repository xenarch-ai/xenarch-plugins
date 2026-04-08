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

class CategorybreakdownController extends ApiController
{
    protected $contentType = 'categorybreakdown';

    public function displayList(): void
    {
        $this->checkAdmin();
        $siteId = XenarchHelper::getParam('site_id');

        if (empty($siteId)) {
            $this->sendJson(['categories' => []]);
            return;
        }

        $api = new ApiClient();
        $result = $api->getCategoryBreakdown($siteId);

        $this->sendJson([
            'categories' => ($result !== null && isset($result['categories'])) ? $result['categories'] : [],
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

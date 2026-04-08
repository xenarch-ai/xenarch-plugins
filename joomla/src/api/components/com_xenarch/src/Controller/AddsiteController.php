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

class AddsiteController extends ApiController
{
    protected $contentType = 'addsite';

    public function add(): void
    {
        $this->checkAdmin();
        $domain = XenarchHelper::getSiteDomain();

        if (empty($domain)) {
            $this->sendJson(['error' => 'Could not detect site domain.'], 400);
            return;
        }

        $api = new ApiClient();
        $result = $api->addSite($domain);

        if ($result === null) {
            $this->sendJson(['error' => 'Failed to add site.'], 400);
            return;
        }

        if (!empty($result['id'])) {
            XenarchHelper::setParam('site_id', $result['id']);
        }
        if (!empty($result['site_token'])) {
            XenarchHelper::setParam('site_token', $result['site_token']);
        }

        $settingsController = new SettingsController(
            ['option' => 'com_xenarch'],
            $this->app,
            $this->input
        );
        $settingsController->displayList();
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

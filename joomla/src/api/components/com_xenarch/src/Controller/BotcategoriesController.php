<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Component\Xenarch\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Xenarch\Component\Xenarch\Administrator\Helper\XenarchHelper;
use Xenarch\Plugin\System\Xenarch\BotDetect;

class BotcategoriesController extends ApiController
{
    protected $contentType = 'botcategories';

    public function displayList(): void
    {
        $this->checkAdmin();
        $this->sendJson(XenarchHelper::getJsonParam('bot_categories', '{}'));
    }

    public function add(): void
    {
        $this->checkAdmin();
        $data = json_decode($this->input->json->getRaw(), true) ?: [];
        $categories = XenarchHelper::getJsonParam('bot_categories', '{}');
        $validKeys = BotDetect::getCategoryKeys();
        $validActions = ['allow', 'charge'];

        foreach ($data as $key => $action) {
            if (in_array($key, $validKeys, true) && in_array($action, $validActions, true)) {
                $categories[$key] = $action;
            }
        }

        XenarchHelper::setParam('bot_categories', json_encode($categories));
        $this->sendJson($categories);
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

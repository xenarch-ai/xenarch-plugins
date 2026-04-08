<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Component\Xenarch\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Xenarch\Component\Xenarch\Administrator\Helper\XenarchHelper;

class BotoverridesController extends ApiController
{
    protected $contentType = 'botoverrides';

    public function displayList(): void
    {
        $this->checkAdmin();
        $this->sendJson(XenarchHelper::getJsonParam('bot_overrides', '{}'));
    }

    public function add(): void
    {
        $this->checkAdmin();
        $data = json_decode($this->input->json->getRaw(), true) ?: [];
        $overrides = XenarchHelper::getJsonParam('bot_overrides', '{}');
        $validActions = ['allow', 'charge'];

        foreach ($data as $signature => $action) {
            $signature = filter_var($signature, FILTER_SANITIZE_SPECIAL_CHARS);
            if ($action === null) {
                unset($overrides[$signature]);
            } elseif (in_array($action, $validActions, true)) {
                $overrides[$signature] = $action;
            }
        }

        XenarchHelper::setParam('bot_overrides', json_encode($overrides));
        $this->sendJson($overrides);
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

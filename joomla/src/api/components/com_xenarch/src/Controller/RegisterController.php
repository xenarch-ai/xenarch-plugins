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

class RegisterController extends ApiController
{
    protected $contentType = 'register';

    public function add(): void
    {
        $this->checkAdmin();
        $data = json_decode($this->input->json->getRaw(), true) ?: [];

        $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            $this->sendJson(['error' => 'Email and password are required.'], 400);
            return;
        }

        $api = new ApiClient();
        $result = $api->register($email, $password);

        if ($result === null) {
            $this->sendJson(['error' => 'Registration failed.'], 400);
            return;
        }

        if (!empty($result['api_key'])) {
            XenarchHelper::setParams([
                'api_key' => $result['api_key'],
                'email'   => $email,
            ]);
        }

        // Return full settings.
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

<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 *
 * Admin-side AJAX controller for the React dashboard.
 * Routes through the Administrator app so the admin session is available.
 * The /api/ controllers remain for external/CLI access via Joomla API tokens.
 */

namespace Xenarch\Component\Xenarch\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Xenarch\Component\Xenarch\Administrator\Helper\XenarchHelper;
use Xenarch\Plugin\System\Xenarch\ApiClient;
use Xenarch\Plugin\System\Xenarch\BotDetect;

class AjaxController extends BaseController
{
    /**
     * Route to the correct handler based on the 'endpoint' query param.
     */
    public function execute($task)
    {
        try {
            // task arrives as just the method name, e.g. 'settings'
            $endpoint = str_replace('ajax.', '', $task);

            $this->checkAuth();

            $method = $this->input->getString('method', $this->input->getMethod());

            switch ($endpoint) {
                case 'settings':
                    $method === 'POST' ? $this->saveSettings() : $this->getSettings();
                    break;
                case 'register':
                    $this->register();
                    break;
                case 'addsite':
                    $this->addSite();
                    break;
                case 'pricingrules':
                    $method === 'PUT' || $method === 'POST' ? $this->savePricingRules() : $this->getPricingRules();
                    break;
                case 'botcategories':
                    $method === 'POST' ? $this->saveBotCategories() : $this->getBotCategories();
                    break;
                case 'botoverrides':
                    $method === 'POST' ? $this->saveBotOverrides() : $this->getBotOverrides();
                    break;
                case 'botsignatures':
                    $this->getBotSignatures();
                    break;
                case 'pagepaths':
                    $this->getPagePaths();
                    break;
                case 'stats':
                    $this->getStats();
                    break;
                case 'transactions':
                    $this->getTransactions();
                    break;
                case 'balance':
                    $this->getBalance();
                    break;
                case 'categorybreakdown':
                    $this->getCategoryBreakdown();
                    break;
                case 'sellconfig':
                    $this->getSellConfig();
                    break;
                case 'selloptions':
                    $this->getSellOptions();
                    break;
                case 'sellquote':
                    $this->createSellQuote();
                    break;
                case 'cashouts':
                    $this->recordCashOut();
                    break;
                default:
                    $this->sendJson(['error' => 'Unknown endpoint.'], 404);
            }
        } catch (\Throwable $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            $this->sendJson(['error' => $e->getMessage()], $code);
        }

        return $this;
    }

    // ── Auth ──────────────────────────────────────────────

    private function checkAuth(): void
    {
        if (!Session::checkToken('request')) {
            throw new \RuntimeException('Invalid CSRF token', 403);
        }

        $user = $this->app->getIdentity();
        if (!$user || $user->guest || !$user->authorise('core.manage', 'com_xenarch')) {
            throw new \RuntimeException('Forbidden', 403);
        }
    }

    // ── Helpers ──────────────────────────────────────────

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?: [];
    }

    private function sendJson(array $data, int $code = 200): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }

    private function getAllSettingsArray(): array
    {
        $params = XenarchHelper::getAllParams();
        $categories = json_decode($params['bot_categories'] ?? '{}', true);
        $overrides = json_decode($params['bot_overrides'] ?? '{}', true);
        $siteUrl = XenarchHelper::getSiteUrl();

        $botSignatureCount = 0;
        if (class_exists('Xenarch\\Plugin\\System\\Xenarch\\BotDetect')) {
            $botSignatureCount = count(BotDetect::getSignatures()) + count(BotDetect::getFetcherSignatures());
        }

        return [
            'api_key'              => !empty($params['api_key'] ?? ''),
            'site_id'              => $params['site_id'] ?? '',
            'site_token'           => $params['site_token'] ?? '',
            'default_price'        => $params['default_price'] ?? '0.003',
            'payout_wallet'        => $params['payout_wallet'] ?? '',
            'gate_unknown_traffic' => $params['gate_unknown_traffic'] ?? '1',
            'gate_enabled'         => $params['gate_enabled'] ?? '1',
            'bot_categories'       => is_array($categories) ? $categories : [],
            'bot_overrides'        => is_array($overrides) ? $overrides : [],
            'wallet_type'          => $params['wallet_type'] ?? '',
            'wallet_network'       => $params['wallet_network'] ?? 'base',
            'domain'               => XenarchHelper::getSiteDomain(),
            'has_wallet'           => !empty($params['payout_wallet'] ?? ''),
            'has_site'             => !empty($params['site_id'] ?? '') && !empty($params['site_token'] ?? ''),
            'bot_signature_count'  => $botSignatureCount,
            'pay_json_url'         => $siteUrl . '/.well-known/pay.json',
            'xenarch_md_url'       => $siteUrl . '/.well-known/xenarch.md',
        ];
    }

    // ── Settings ─────────────────────────────────────────

    private function getSettings(): void
    {
        $this->sendJson($this->getAllSettingsArray());
    }

    private function saveSettings(): void
    {
        $data = $this->getJsonBody();

        $allowed = [
            'xenarch_default_price'       => 'default_price',
            'xenarch_payout_wallet'       => 'payout_wallet',
            'xenarch_gate_unknown_traffic' => 'gate_unknown_traffic',
            'xenarch_gate_enabled'        => 'gate_enabled',
            'xenarch_wallet_type'         => 'wallet_type',
            'xenarch_wallet_network'      => 'wallet_network',
        ];

        $updates = [];
        foreach ($allowed as $requestKey => $paramKey) {
            if (isset($data[$requestKey])) {
                $updates[$paramKey] = filter_var($data[$requestKey], FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }

        // Normalize wallet address to lowercase — ethers.js rejects mixed-case
        // that doesn't match EIP-55 checksum. Lowercase is always accepted.
        if (isset($updates['payout_wallet'])) {
            $updates['payout_wallet'] = strtolower($updates['payout_wallet']);
        }

        if (!empty($updates)) {
            XenarchHelper::setParams($updates);
        }

        // Auto-register with platform if wallet just set and no api_key yet.
        $params = XenarchHelper::getAllParams();
        if (!empty($params['payout_wallet']) && empty($params['api_key'])) {
            $this->autoRegister();
            $params = XenarchHelper::getAllParams(); // Refresh after registration.
        }

        // Sync pricing to platform.
        $siteId = $params['site_id'] ?? '';
        if (!empty($siteId) && isset($data['xenarch_default_price'])) {
            $rules = json_decode($params['pricing_rules'] ?? '[]', true);
            $apiRules = [];
            if (is_array($rules)) {
                foreach ($rules as $rule) {
                    if (!empty($rule['path_contains']) && isset($rule['price_usd'])) {
                        $apiRules[] = ['path' => '*' . $rule['path_contains'] . '*', 'price_usd' => (float) $rule['price_usd']];
                    }
                }
            }
            $api = new ApiClient();
            $api->updatePricing($siteId, (float) ($params['default_price'] ?? '0.003'), $apiRules);
        }

        // Sync payout wallet to platform.
        if (isset($data['xenarch_payout_wallet']) && !empty($data['xenarch_payout_wallet']) && !empty($params['api_key'])) {
            $api = $api ?? new ApiClient();
            $api->updatePayout($data['xenarch_payout_wallet']);
        }

        $this->sendJson($this->getAllSettingsArray());
    }

    /**
     * Auto-register with the platform: create publisher account + add site.
     * Called once when a wallet is saved and no api_key exists yet.
     */
    private function autoRegister(): void
    {
        try {
            $domain = XenarchHelper::getSiteDomain();
            $email = 'auto+' . $domain . '@xenarch.dev';
            $password = bin2hex(random_bytes(20));

            $api = new ApiClient();
            $result = $api->register($email, $password);

            if (!empty($result['api_key'])) {
                XenarchHelper::setParams([
                    'api_key' => $result['api_key'],
                    'email'   => $email,
                ]);

                // Now add the site.
                $siteResult = $api->addSite($domain);
                if (!empty($siteResult['id'])) {
                    $siteParams = ['site_id' => $siteResult['id']];
                    if (!empty($siteResult['site_token'])) {
                        $siteParams['site_token'] = $siteResult['site_token'];
                    }
                    XenarchHelper::setParams($siteParams);
                }
            }
        } catch (\Throwable $e) {
            // Silently fail — registration will retry on next settings save.
        }
    }

    // ── Registration ─────────────────────────────────────

    private function register(): void
    {
        $data = $this->getJsonBody();
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

        $this->sendJson($this->getAllSettingsArray());
    }

    // ── Add Site ─────────────────────────────────────────

    private function addSite(): void
    {
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

        $this->sendJson($this->getAllSettingsArray());
    }

    // ── Pricing Rules ────────────────────────────────────

    private function getPricingRules(): void
    {
        $this->sendJson(['rules' => XenarchHelper::getJsonParam('pricing_rules', '[]')]);
    }

    private function savePricingRules(): void
    {
        $data = $this->getJsonBody();
        $rules = $data['rules'] ?? [];

        $cleanRules = [];
        foreach ($rules as $rule) {
            if (isset($rule['path_contains']) && isset($rule['price_usd'])) {
                $cleanRules[] = [
                    'path_contains' => filter_var($rule['path_contains'], FILTER_SANITIZE_SPECIAL_CHARS),
                    'price_usd'     => filter_var($rule['price_usd'], FILTER_SANITIZE_SPECIAL_CHARS),
                ];
            }
        }

        XenarchHelper::setParam('pricing_rules', json_encode($cleanRules));

        $params = XenarchHelper::getAllParams();
        $siteId = $params['site_id'] ?? '';
        if (!empty($siteId)) {
            $apiRules = [];
            foreach ($cleanRules as $rule) {
                $apiRules[] = ['path' => '*' . $rule['path_contains'] . '*', 'price_usd' => (float) $rule['price_usd']];
            }
            $api = new ApiClient();
            $api->updatePricing($siteId, (float) ($params['default_price'] ?? '0.003'), $apiRules);
        }

        $this->sendJson(['rules' => $cleanRules]);
    }

    // ── Bot Categories ───────────────────────────────────

    private function getBotCategories(): void
    {
        $this->sendJson(XenarchHelper::getJsonParam('bot_categories', '{}'));
    }

    private function saveBotCategories(): void
    {
        $data = $this->getJsonBody();
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

    // ── Bot Overrides ────────────────────────────────────

    private function getBotOverrides(): void
    {
        $this->sendJson(XenarchHelper::getJsonParam('bot_overrides', '{}'));
    }

    private function saveBotOverrides(): void
    {
        $data = $this->getJsonBody();
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

    // ── Bot Signatures ───────────────────────────────────

    private function getBotSignatures(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['signature', 'category', 'company', 'first_seen', 'last_seen', 'hit_count'])
            ->from($db->quoteName('#__xenarch_bot_log'));
        $db->setQuery($query);
        $logRows = $db->loadAssocList() ?: [];

        $logMap = [];
        foreach ($logRows as $row) {
            $logMap[$row['signature']] = $row;
        }

        $static = BotDetect::getAllSignatureMetadata();
        $result = [];
        $seenSigs = [];

        foreach ($static as $sig) {
            $log = $logMap[$sig['name']] ?? null;
            $result[] = [
                'name'       => $sig['name'],
                'category'   => $sig['category'],
                'company'    => $sig['company'],
                'last_seen'  => $log ? $log['last_seen'] : null,
                'hit_count'  => $log ? (int) $log['hit_count'] : 0,
                'first_seen' => $log ? $log['first_seen'] : null,
            ];
            $seenSigs[$sig['name']] = true;
        }

        foreach ($logMap as $sig => $row) {
            if (isset($seenSigs[$sig])) continue;
            $result[] = [
                'name'       => $sig,
                'category'   => $row['category'],
                'company'    => $row['company'],
                'last_seen'  => $row['last_seen'],
                'hit_count'  => (int) $row['hit_count'],
                'first_seen' => $row['first_seen'],
            ];
        }

        $this->sendJson(['signatures' => $result]);
    }

    // ── Page Paths ───────────────────────────────────────

    private function getPagePaths(): void
    {
        $query = $this->input->getString('q', '');

        if (empty($query) || strlen($query) < 2) {
            $this->sendJson(['paths' => []]);
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $search = $db->quote('%' . $db->escape($query, true) . '%');

        $dbQuery = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('title'), $db->quoteName('alias'), $db->quoteName('catid')])
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('state') . ' = 1')
            ->where('(' . $db->quoteName('title') . ' LIKE ' . $search . ' OR ' . $db->quoteName('alias') . ' LIKE ' . $search . ')')
            ->setLimit(10);
        $db->setQuery($dbQuery);
        $articles = $db->loadAssocList() ?: [];

        $results = [];
        foreach ($articles as $article) {
            $results[] = [
                'path'  => '/' . $article['alias'],
                'title' => $article['title'],
            ];
        }

        $this->sendJson(['paths' => $results]);
    }

    // ── Stats ────────────────────────────────────────────

    private function getStats(): void
    {
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

    // ── Transactions ─────────────────────────────────────

    private function getTransactions(): void
    {
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

    // ── Balance ──────────────────────────────────────────

    private function getBalance(): void
    {
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

    // ── Category Breakdown ───────────────────────────────

    private function getCategoryBreakdown(): void
    {
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

    // ── Sell Config ──────────────────────────────────────

    private function getSellConfig(): void
    {
        $api = new ApiClient();
        $result = $api->getSellConfig();

        if ($result === null) {
            $this->sendJson(['error' => 'Failed to fetch sell config.'], 502);
            return;
        }

        $this->sendJson($result);
    }

    // ── Sell Options ─────────────────────────────────────

    private function getSellOptions(): void
    {
        $country = $this->input->getString('country', '');
        if (empty($country)) {
            $this->sendJson(['error' => 'Country is required.'], 400);
            return;
        }

        $api = new ApiClient();
        $result = $api->getSellOptions($country);

        if ($result === null) {
            $this->sendJson(['error' => 'Failed to fetch sell options.'], 502);
            return;
        }

        $this->sendJson($result);
    }

    // ── Sell Quote ────────────────────────────────────────

    private function createSellQuote(): void
    {
        $data = $this->getJsonBody();
        $amountUsd = filter_var($data['amount_usd'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        $country = filter_var($data['country'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        $paymentMethod = filter_var($data['payment_method'] ?? 'FIAT_WALLET', FILTER_SANITIZE_SPECIAL_CHARS);

        if (empty($amountUsd) || empty($country)) {
            $this->sendJson(['error' => 'Amount and country are required.'], 400);
            return;
        }

        $siteId = XenarchHelper::getParam('site_id');
        if (empty($siteId)) {
            $this->sendJson(['error' => 'Site not configured.'], 400);
            return;
        }

        $api = new ApiClient();
        $result = $api->createSellQuote($siteId, $amountUsd, $country, $paymentMethod);

        if ($result === null) {
            $this->sendJson(['error' => 'Failed to create sell quote.'], 502);
            return;
        }

        $this->sendJson($result);
    }

    // ── Cash Outs ────────────────────────────────────────

    private function recordCashOut(): void
    {
        $data = $this->getJsonBody();
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
}

<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Component\Xenarch\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Xenarch\Plugin\System\Xenarch\BotDetect;

class BotsignaturesController extends ApiController
{
    protected $contentType = 'botsignatures';

    public function displayList(): void
    {
        $this->checkAdmin();

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

        // Add dynamically detected bots not in static list.
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

<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Component\Xenarch\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;

class PagepathsController extends ApiController
{
    protected $contentType = 'pagepaths';

    /**
     * GET /page-paths?q=... — autocomplete for pricing rule path input.
     * Queries Joomla #__content articles instead of WP posts.
     */
    public function displayList(): void
    {
        $this->checkAdmin();
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
            // Build a simple path from the alias.
            $path = '/' . $article['alias'];
            $results[] = [
                'path'  => $path,
                'title' => $article['title'],
            ];
        }

        $this->sendJson(['paths' => $results]);
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

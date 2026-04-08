<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

namespace Xenarch\Plugin\WebServices\Xenarch\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

/**
 * Web Services plugin — registers REST API routes for the Xenarch admin panel.
 *
 * Routes are prefixed with /v1/xenarch/ and map to controllers in
 * api/components/com_xenarch/src/Controller/.
 */
class Xenarch extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeApiRoute' => 'onBeforeApiRoute',
        ];
    }

    public function onBeforeApiRoute(&$event): void
    {
        /** @var ApiRouter $router */
        $router = $event->getArgument('router', $event[0] ?? null);

        if (!$router instanceof ApiRouter) {
            return;
        }

        $defaults = [
            'component' => 'com_xenarch',
            'public'    => false,
        ];

        // Settings
        $router->createCRUDRoutes(
            'v1/xenarch/settings',
            'settings',
            $defaults
        );

        // Register
        $router->addRoute(new Route(
            ['POST'],
            'v1/xenarch/register',
            'register.add',
            [],
            $defaults
        ));

        // Add site
        $router->addRoute(new Route(
            ['POST'],
            'v1/xenarch/add-site',
            'addsite.add',
            [],
            $defaults
        ));

        // Pricing rules
        $router->createCRUDRoutes(
            'v1/xenarch/pricing-rules',
            'pricingrules',
            $defaults
        );

        // Stats (proxy)
        $router->addRoute(new Route(
            ['GET'],
            'v1/xenarch/stats',
            'stats.displayList',
            [],
            $defaults
        ));

        // Transactions (proxy)
        $router->addRoute(new Route(
            ['GET'],
            'v1/xenarch/transactions',
            'transactions.displayList',
            [],
            $defaults
        ));

        // Bot categories
        $router->createCRUDRoutes(
            'v1/xenarch/bot-categories',
            'botcategories',
            $defaults
        );

        // Bot overrides
        $router->createCRUDRoutes(
            'v1/xenarch/bot-overrides',
            'botoverrides',
            $defaults
        );

        // Bot signatures (read-only)
        $router->addRoute(new Route(
            ['GET'],
            'v1/xenarch/bot-signatures',
            'botsignatures.displayList',
            [],
            $defaults
        ));

        // Page paths autocomplete
        $router->addRoute(new Route(
            ['GET'],
            'v1/xenarch/page-paths',
            'pagepaths.displayList',
            [],
            $defaults
        ));

        // Balance
        $router->addRoute(new Route(
            ['GET'],
            'v1/xenarch/balance',
            'balance.displayList',
            [],
            $defaults
        ));

        // Category breakdown
        $router->addRoute(new Route(
            ['GET'],
            'v1/xenarch/category-breakdown',
            'categorybreakdown.displayList',
            [],
            $defaults
        ));

        // Sell config
        $router->addRoute(new Route(
            ['GET'],
            'v1/xenarch/sell-config',
            'sellconfig.displayList',
            [],
            $defaults
        ));

        // Sell options
        $router->addRoute(new Route(
            ['GET'],
            'v1/xenarch/sell-options',
            'selloptions.displayList',
            [],
            $defaults
        ));

        // Sell quote
        $router->addRoute(new Route(
            ['POST'],
            'v1/xenarch/sell-quote',
            'sellquote.add',
            [],
            $defaults
        ));

        // Cash outs
        $router->addRoute(new Route(
            ['POST'],
            'v1/xenarch/cash-outs',
            'cashouts.add',
            [],
            $defaults
        ));
    }
}

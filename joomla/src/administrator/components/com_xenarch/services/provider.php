<?php
/**
 * @package    Xenarch
 * @license    GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Xenarch\Component\Xenarch\Administrator\Extension\XenarchComponent;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\Xenarch\\Component\\Xenarch'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Xenarch\\Component\\Xenarch'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new XenarchComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};

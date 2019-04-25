<?php

namespace Monolith\Module\Unicat\Listener;

use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

class ControllerListener
{
    use ContainerAwareTrait;

    /**
     * Constructor.
     *
     * @todo инжектить юникат
     */
    public function __construct()
    {

    }

    public function onController(FilterControllerEvent $event)
    {
        $controller = $event->getController();

        $usedTraits = class_uses($controller[0]);

        if (isset($usedTraits['Monolith\Module\Unicat\Controller\UnicatTrait']) and method_exists($controller[0], 'getNode')) {
            $configuration = $this->container->get('unicat')->getConfigurationManager($controller[0]->getNode()->getParam('configuration_id'));
            $controller[0]->setUnicat($configuration);
        }
    }
}

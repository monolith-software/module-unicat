<?php

namespace Monolith\Module\Unicat\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class UnicatAdminMenu
{
    use ContainerAwareTrait;

    /**
     * @param FactoryInterface $factory
     * @param array $options
     *
     * @return ItemInterface
     */
    public function configuration(FactoryInterface $factory, array $options)
    {
        $menu = $factory->createItem('unicat_configuration');

        $menu->setChildrenAttribute('class', isset($options['class']) ? $options['class'] : 'nav nav-tabs');

        /** @var UnicatConfiguration $configuration */
        $configuration = $options['configuration']->getName();

        // @todo кастомизация имени ссылки
        $item = $menu->addChild($options['configuration']->getTitle(), ['route' => 'unicat_admin.configuration', 'routeParameters' => ['configuration' => $configuration]]);
        $item->setExtra('translation_domain', false);
        //$item->setLinkAttribute('class', 'btn');

        $menu->addChild('Taxonomy',     ['route' => 'unicat_admin.taxonomies_index', 'routeParameters' => ['configuration' => $configuration]]);
        $menu->addChild('Attributes',   ['route' => 'unicat_admin.attributes_index', 'routeParameters' => ['configuration' => $configuration]]);
        $menu->addChild('Items types',  ['route' => 'unicat_admin.items_types',      'routeParameters' => ['configuration' => $configuration]]);
        //$menu->addChild('Link names',   ['uri' => '#']); // @todo
        //$menu->addChild('Link names',   ['route' => 'unicat_admin.properties_index',    'routeParameters' => ['configuration' => $configuration]]);
        $menu->addChild('Settings',     ['route' => 'unicat_admin.configuration.settings', 'routeParameters' => ['configuration' => $configuration]]);

        return $menu;
    }
}

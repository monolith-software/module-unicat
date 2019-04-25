<?php

namespace Monolith\Module\Unicat\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Monolith\Module\Unicat\Model\TaxonModel;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class TaxonMenu implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @param FactoryInterface $factory
     * @param array            $options
     *
     * @return ItemInterface
     */
    public function tree(FactoryInterface $factory, array $options)
    {
        if (!isset($options['taxonClass'])) {
            throw new \Exception('Надо указать taxonClass в опциях');
        }

        if (!isset($options['routeName'])) {
            throw new \Exception('Надо указать routeName в опциях');
        }

        $menu = $factory->createItem('taxons');

        if (!empty($options['css_class'])) {
            $menu->setChildrenAttribute('class', $options['css_class']);
        }

        $this->addChild($menu, null, $options);
        $this->removeFactory($menu);

        return $menu;
    }

    /**
     * Рекурсивный метод для удаления фабрики, что позволяет кешировать объект меню.
     *
     * @param ItemInterface $menu
     */
    protected function removeFactory(ItemInterface $menu)
    {
        $menu->setFactory(new DummyFactory());

        foreach ($menu->getChildren() as $subMenu) {
            $this->removeFactory($subMenu);
        }
    }

    /**
     * Рекурсивное построение дерева.
     *
     * @param ItemInterface   $menu
     * @param TaxonModel|null $parent
     * @param array           $options
     */
    protected function addChild(ItemInterface $menu, TaxonModel $parent = null, array $options)
    {
        $taxons = $this->container->get('doctrine.orm.entity_manager')->getRepository($options['taxonClass'])->findBy([
                'parent'     => $parent,
                'is_enabled' => true,
                'taxonomy'  => $options['taxonomy'],
            ], ['position' => 'ASC']);

        /** @var TaxonModel $taxon */
        foreach ($taxons as $taxon) {
            $uri = $this->container->get('router')->generate($options['routeName'], ['slug' => $taxon->getSlugFull()]).'/';
            $menu->addChild($taxon->getSlug(), [
                'label' => $taxon->getTitle(),
                /*
                'linkAttributes' => [
                    'title' => $taxon->getSlug(),
                ],
                */
                'uri' => $uri,
            ]);

            /** @var ItemInterface $sub_menu */
            $sub_menu = $menu[$taxon->getSlug()];

            $this->addChild($sub_menu, $taxon, $options);
        }
    }

    /**
     * @param FactoryInterface $factory
     * @param array            $options
     *
     * @return ItemInterface
     */
    public function adminTree(FactoryInterface $factory, array $options)
    {
        if (!isset($options['taxonClass'])) {
            throw new \Exception('Надо указать taxonClass в опциях');
        }

        $menu = $factory->createItem('taxons');
        $this->addChildToAdminTree($menu, null, $options);

        return $menu;
    }

    /**
     * Рекурсивное построение дерева для админки.
     *
     * @param ItemInterface   $menu
     * @param TaxonModel|null $parent
     * @param array           $options
     */
    protected function addChildToAdminTree(ItemInterface $menu, TaxonModel $parent = null, $options)
    {
        $taxons = $this->container->get('doctrine')->getManager()->getRepository($options['taxonClass'])->findBy([
                'parent'    => $parent,
                'taxonomy' => $options['taxonomy'],
            ], ['position' => 'ASC']);

        /** @var TaxonModel $taxon */
        foreach ($taxons as $taxon) {
            $uri = $this->container->get('router')->generate('unicat_admin.taxon', [
                'id'            => $taxon->getId(),
                'taxonomy_name' => $taxon->getTaxonomy()->getName(),
                'configuration' => $taxon->getTaxonomy()->getConfiguration()->getName(),
            ]);
            $newItem = $menu->addChild($taxon->getSlug(), [
                'label' => $taxon->getTitle(),
                'linkAttributes' => [
                    'title' => $taxon->getSlug(),
                ],
                'uri' => $uri,
            ]);

            if (false === $taxon->getIsEnabled()) {
                $newItem->setAttribute('style', 'text-decoration: line-through;');
            }

            /** @var ItemInterface $sub_menu */
            $sub_menu = $menu[$taxon->getSlug()];

            $this->addChildToAdminTree($sub_menu, $taxon, $options);
        }
    }
}

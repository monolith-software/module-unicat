<?php

declare(strict_types=1);

namespace Monolith\Module\Unicat;

use Knp\Menu\MenuItem;
use Monolith\Bundle\CMSBundle\Module\ModuleBundle;
use Monolith\Module\Unicat\DependencyInjection\Compiler\FormPass;
use Monolith\Module\Unicat\DependencyInjection\UnicatExtension;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class UnicatModuleBundle extends ModuleBundle
{
    /**
     * Получить виджеты для рабочего стола.
     *
     * @return array
     */
    public function getDashboard()
    {
        $em      = $this->container->get('doctrine.orm.entity_manager');
        $r       = $this->container->get('router');
        $configs = $em->getRepository(UnicatConfiguration::class)->findAll();

        $data = [
            'title' => 'Юникат',
            'items' => [],
        ];

        foreach ($configs as $config) {
            $data['items']['manage_config_'.$config->getId()] = [
                'title' => 'Конфигурация: <b>'.$config->getTitle().'</b>',
                'descr' => '',
                'url' => $r->generate('unicat_admin.configuration', ['configuration' => $config->getName()]),
            ];
        }

        return $data;
    }

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new FormPass());
    }

    /**
     * @return UnicatExtension
     *
    public function getContainerExtension()
    {
        return new UnicatExtension();
    }
     */

    /**
     * @return array
     *
     * @todo
     */
    public function getWidgets()
    {
        return [
            'taxon_tree' => [
                'class' => 'UnicatWidget:taxonTree',
            ],
            'get_items' => [
                'class' => 'UnicatWidget:getItems',
            ],
        ];
    }

    /**
     * @param MenuItem $menu
     * @param array $extras
     *
     * @return MenuItem
     */
    public function buildAdminMenu(MenuItem $menu, array $extras = ['beforeCode' => '<i class="fa fa-angle-right"></i>'])
    {
        if ($this->hasAdmin()) {
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->container->get('doctrine.orm.entity_manager');

            $configurations = $em->getRepository(UnicatConfiguration::class)->findAll();

            if (empty($configurations)) {
                $extras = [
                    'beforeCode' => '<i class="fa fa-cubes"></i>',
                    'translation_domain' => false,
                ];

                $menu->addChild($this->getShortName(), [
                    'uri' => $this->container->get('router')->generate('cms_admin_index').$this->getShortName().'/'
                ])
                    ->setExtras($extras)
                ;
            } else {
                /*
                $extras = [
                    'afterCode'  => '<i class="fa fa-angle-left pull-right"></i>',
                    'beforeCode' => '<i class="fa fa-cubes"></i>',
                    'translation_domain' => false,
                ];

                $submenu = $menu->addChild($this->getShortName(), ['uri' => $this->container->get('router')->generate('cms_admin_index').$this->getShortName().'/'])
                    ->setAttribute('class', 'treeview')
                    ->setExtras($extras)
                ;

                $submenu->setChildrenAttribute('class', 'treeview-menu');
                */

                foreach ($configurations as $uc) {
                    $beforeCode = '<i class="fa fa-angle-right"></i>';

                    if (!empty($uc->getIcon())) {
                        $beforeCode = '<i class="fa fa-'.$uc->getIcon().'"></i>';
                    }

                    $menu->addChild($uc->getTitle(), [
                        'route' => 'unicat_admin.configuration',
                        'routeParameters' => ['configuration' => $uc->getName()],
                    ])->setExtras([
                        'beforeCode' => $beforeCode,
                        'translation_domain' => false,
                    ]);
                }
            }
        }

        return $menu;
    }
}

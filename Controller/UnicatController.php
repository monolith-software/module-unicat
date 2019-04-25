<?php

declare(strict_types=1);

namespace Monolith\Module\Unicat\Controller;

use Monolith\Bundle\CMSBundle\Annotation\NodePropertiesForm;
use Monolith\Bundle\CMSBundle\Entity\Node;
use Monolith\Module\Unicat\Service\UnicatConfigurationManager;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Smart\CoreBundle\Controller\Controller;
use Monolith\Bundle\CMSBundle\Module\CacheTrait;
use Monolith\Module\Unicat\Entity\UnicatItemType;
use Monolith\Module\Unicat\Model\TaxonModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Yaml\Yaml;

class UnicatController extends Controller
{
    use CacheTrait;
//    use UnicatTrait;

//    protected $configuration_id;
    protected $use_item_id_as_slug;

    /**
     * В формате YAML.
     *
     * @var string
     */
    protected $params;

    /**
     * @param Request    $request
     * @param null       $slug
     * @param int|null   $page
     * @param mixed|null $options
     *
     * @return Response
     *
     * @NodePropertiesForm("NodePropertiesFormType")
     */
    public function indexAction(Request $request, Node $node, $configuration_id, $slug = null, $page = null, $options = null, $params = null)
    {
        if (null === $page) {
            $page = $request->query->get('page', 1);
        }

        $ucm = $this->get('unicat')->getConfigurationManager($configuration_id);

        try {
            $requestedTaxons = $ucm->findTaxonsBySlug($slug, $ucm->getDefaultTaxonomy());
        } catch (NotFoundHttpException $e) {
            $requestedTaxons = [];
        }

        foreach ($requestedTaxons as $taxon) {
            $this->get('cms.breadcrumbs')->add($this->generateUrl('unicat.index', ['slug' => $taxon->getSlugFull()]).'/', $taxon->getTitle());
        }

        $lastTaxon = end($requestedTaxons);

        if ($lastTaxon instanceof TaxonModel) {
            $this->get('html')->setMetas($lastTaxon->getMeta());
            $childenTaxons = $ucm->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => $lastTaxon,
                'taxonomy'  => $ucm->getDefaultTaxonomy(),
            ], ['position' => 'ASC']);
        } else {
            $childenTaxons = $ucm->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => null,
                'taxonomy'  => $ucm->getDefaultTaxonomy(),
            ], ['position' => 'ASC']);
        }

        $this->buildFrontControlForTaxon($node, $ucm, $lastTaxon);

        $cacheKey = md5('smart_module.unicat.yaml_params'.$node->getId());

        $params_yaml = $params;
        if (null === $params = $this->getCacheService()->get($cacheKey)) {
            $params = Yaml::parse($params_yaml);

            $this->getCacheService()->set($cacheKey, $params, ['smart_module.unicat', 'node_'.$node->getId(), 'node']);
        }

        // Автоматическое определение типа итема
        if (!isset($params['type'])) {
            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->get('doctrine.orm.entity_manager');
            $itemType = $em->getRepository(UnicatItemType::class)->findOneBy([
                'configuration' => $ucm->getConfiguration()
            ], ['position' => 'ASC']);

            $params['type'] = $itemType->getName();
        }

        /** @var Pagerfanta $pagerfanta */
        $pagerfanta = null;

        if ($slug) {
            if ($lastTaxon) {
                $params['taxonomy'][] = [$lastTaxon->getTaxonomy()->getName(), 'IN', $lastTaxon->getId()];

                $unicatResult = $ucm->getData($params);
                $pagerfanta = $unicatResult['items'];
            }
        } elseif ($ucm->getConfiguration()->isInheritance()) {
            if (!empty($params)) {
                $unicatResult = $ucm->getData($params);
                $pagerfanta = $unicatResult['items'];
            }
        }

        if (!empty($pagerfanta)) {
            $pagerfanta->setMaxPerPage($ucm->getConfiguration()->getItemsPerPage());

            try {
                $pagerfanta->setCurrentPage($page);
            } catch (NotValidCurrentPageException $e) {
                throw $this->createNotFoundException('Такой страницы не найдено');
            }
        }

        return $this->render('@UnicatModule/index.html.twig', [
            'mode'          => 'list',
            'attributes'    => $ucm->getAttributes(),
            'configuration' => $ucm->getConfiguration(),
            'lastTaxon'     => $lastTaxon,
            'childenTaxons' => $childenTaxons,
            'options'       => $options,
            'pagerfanta'    => $pagerfanta,
            'slug'          => $slug,
        ]);
    }

    /**
     * @param string|null $taxonomySlug
     * @param string $itemSlug
     *
     * @return Response
     */
    public function itemAction(Node $node, $configuration_id, $taxonomySlug = null, $itemSlug)
    {
        $ucm = $this->get('unicat')->getConfigurationManager($configuration_id);

        $requestedTaxons = $ucm->findTaxonsBySlug($taxonomySlug, $ucm->getDefaultTaxonomy());

        foreach ($requestedTaxons as $taxon) {
            $this->get('cms.breadcrumbs')->add($this->generateUrl('unicat.index', ['slug' => $taxon->getSlugFull()]).'/', $taxon->getTitle());
        }

        $lastTaxon = end($requestedTaxons);

        /*
        if ($lastTaxon instanceof TaxonModel) {
            $childenTaxons = $ucm->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => $lastTaxon,
                'taxonomy'   => $ucm->getDefaultTaxonomy(),
            ]);
        } else {
            $childenTaxons = $ucm->getTaxonRepository()->findBy([
                'is_enabled' => true,
                'parent'     => null,
                'taxonomy'   => $ucm->getDefaultTaxonomy(),
            ]);
        }
        */

        $item = $ucm->findItem($itemSlug, $this->use_item_id_as_slug); // @todo !!! use_item_id_as_slug

        if (empty($item)) {
            throw $this->createNotFoundException();
        }

        $this->get('html')->setMetas($item->getMeta());


        $this->get('cms.breadcrumbs')->add($this->generateUrl('unicat.item', [
//                'slug' => empty($lastTaxon) ? '' : $lastTaxon->getSlugFull(),
                'itemSlug' => $item->getSlug(),
            ]), $item->getAttribute('title', $item->getSlug())); // @todo сделать настраиваемое поле для ХК.

        $node->addFrontControl('edit')
            ->setTitle('Редактировать')
            ->setUri($this->generateUrl('unicat_admin.item_edit', ['configuration' => $ucm->getConfiguration()->getName(), 'id' => $item->getId()]));

        return $this->render('@UnicatModule/item.html.twig', [
            'mode'          => 'view',
            'attributes'    => $ucm->getAttributes(),
            'item'          => $item,
//            'lastTaxon'      => $lastTaxon,
//            'childenTaxons' => $childenTaxons,
        ]);
    }

    /**
     * @param TaxonModel|false $lastTaxon
     */
    protected function buildFrontControlForTaxon(Node $node, UnicatConfigurationManager $ucm, $lastTaxon = false)
    {
        $node->addFrontControl('create_item')
            ->setTitle('Добавить запись')
            ->setUri($this->generateUrl('unicat_admin.item_create_in_taxon', [
                'configuration'    => $ucm->getConfiguration()->getName(),
                'default_taxon_id' => empty($lastTaxon) ? 0 : $lastTaxon->getId(),
            ]));

        if (!empty($lastTaxon)) {
            $node->addFrontControl('create_taxon')
                ->setIsDefault(false)
                ->setTitle('Создать Taxon')
                ->setUri($this->generateUrl('unicat_admin.taxonomy_with_parent_id', [
                    'configuration' => $ucm->getConfiguration()->getName(),
                    'parent_id'     => empty($lastTaxon) ? 0 : $lastTaxon->getId(),
                    'id'            => $lastTaxon->getTaxonomy()->getId(),
                ]));

            $node->addFrontControl('edit_taxon')
                ->setIsDefault(false)
                ->setTitle('Редактировать Taxon')
                ->setUri($this->generateUrl('unicat_admin.taxon', [
                    'configuration' => $ucm->getConfiguration()->getName(),
                    'id'            => $lastTaxon->getId(),
                    'taxonomy_name' => $lastTaxon->getTaxonomy()->getName(),
                ]));
        }

        $node->addFrontControl('manage_configuration')
            ->setIsDefault(false)
            ->setTitle('Управление каталогом')
            ->setUri($this->generateUrl('unicat_admin.configuration', ['configuration' => $ucm->getConfiguration()->getName()]));
    }
}

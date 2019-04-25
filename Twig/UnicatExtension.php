<?php

declare(strict_types=1);

namespace Monolith\Module\Unicat\Twig;

use Monolith\Module\Unicat\Entity\UnicatAttribute;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Monolith\Module\Unicat\Model\ItemModel;
use Monolith\Module\Unicat\Model\TaxonModel;
use Monolith\Module\Unicat\Service\UnicatConfigurationManager;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UnicatExtension extends AbstractExtension
{
    use ContainerAwareTrait;

    /**
     * Временное хранилище для рекурсии.
     *
     * @var array
     */
    protected $tmp_data = [];

    /**
     * UnicatExtension constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('unicat_current_configuration',  [$this, 'getUnicatCurrentConfiguration']),
            new TwigFunction('unicat_get_taxons_by_taxonomy', [$this, 'getTaxonsByTaxonomy']),
            new TwigFunction('unicat_get_items',              [$this, 'getItems']),
            new TwigFunction('unicat_get_attr_choice_value',  [$this, 'getAttrChoiceValue']),
            new TwigFunction('unicat_get_attrs_by_type',      [$this, 'getAttrsByType']),
            new TwigFunction('get_yandex_map_objects',        [$this, 'getYandexMapObjects']),
        ];
    }

    /**
     * @return null|\Monolith\Module\Unicat\Entity\UnicatConfiguration
     */
    public function getUnicatCurrentConfiguration()
    {
        return $this->container->get('unicat')->getCurrentConfiguration();
    }

    /**
     * @param ItemModel|null $item
     * @param string         $attr
     *
     * @return array
     */
    public function getAttrChoiceValue(ItemModel $item = null, $attr)
    {
        if (empty($item)) {
            return [];
        }

        $unicat = $this->container->get('unicat');
        $ucm = $unicat->getCurrentConfigurationManager();

        $data = null;

        $attributes = $ucm->getAttributes();

        /** @var UnicatAttribute $a */
        $a = $attributes[$attr];

        $params = $a->getParams();

        if (isset($params['form']['choices'])) {
            $params = array_flip($params['form']['choices']);

            $data = $params[$item->getAttr($attr)];
        }

        return $data;
    }

    /**
     * @param int|string $configuration
     * @param int|string $id
     * @param bool       $tree
     * @param bool       $is_array
     *
     * @return TaxonModel[]|array
     */
    public function getTaxonsByTaxonomy($configuration, $id, $tree = false, $is_array = false)
    {
        $unicat = $this->container->get('unicat');

        $ucm = $unicat->getConfigurationManager($configuration);

        $taxonomy = null;

        if (is_numeric($id)) {
            $taxonomy = $unicat->getTaxonomy($id);
        }

        if (empty($taxonomy)) {
            $taxonomy = $unicat->getTaxonomyRepository()->findOneBy(['name' => $id]);
        }

        if ($taxonomy) {
            // @todo если нода не подключена - вываливается исключение.

            if ($tree) {
                $taxons = $this->buildTaxonsTree($ucm, $taxonomy, null, $is_array);
            } else {
                $taxons = $ucm->getTaxonRepository()->findBy(['taxonomy' => $taxonomy], ['position' => 'ASC']);
            }

            return $taxons;
        }

        return [];
    }

    /**
     * @param UnicatConfigurationManager $ucm
     * @param UnicatTaxonomy  $taxonomy
     * @param TaxonModel|null $parent
     * @param bool            $is_array
     *
     * @return TaxonModel[]|array
     */
    protected function buildTaxonsTree(UnicatConfigurationManager $ucm, UnicatTaxonomy $taxonomy, TaxonModel $parent = null, $is_array = false)
    {
        $q = $ucm->getTaxonRepository()->getFindByQuery([
            'taxonomy' => $taxonomy,
            'parent' => $parent,
            'is_enabled' => true,
        ], ['position' => 'ASC']);

        if ($is_array) {
            $data = [];

            /** @var TaxonModel $taxon */
            foreach ($q->getResult() as $taxon) {

                $data[$taxon->getId()] = [
                    'id' => $taxon->getId(),
                    'title' => $taxon->getTitle(),
                    'slug' => $taxon->getSlug(),
                    'slug_full' => $taxon->getSlugFull(),
                    'meta' => $taxon->getMeta(),
                    'attrs' => $taxon->getProperties(),
                ];

                $data[$taxon->getId()]['children'] = $this->buildTaxonsTree($ucm, $taxonomy, $taxon, $is_array);
            }

            return $data;
        }

        return $q->getResult();
    }

    /**
     * @param int|string $configuration
     * @param array      $requestArray
     * @param bool       $hydrateObject
     *
     * @return array|\Monolith\Module\Unicat\Model\ItemModel[]
     */
    public function getItems($configuration, array $requestArray, $hydrateObject = true)
    {
        $ucm = $this->container->get('unicat')->getConfigurationManager($configuration);

        return $ucm->getData($requestArray, false, $hydrateObject);
    }

    /**
     * @param $configuration
     *
     * @return \stdClass
     */
    public function getYandexMapObjects($configuration)
    {
        $ucm = $this->container->get('unicat')->getConfigurationManager($configuration);

        $responseData = $ucm->getData([
            'type' => 'category',
        ]);

        $objects = new \stdClass();
        $objectsCount = 1;
        /** @var ItemModel $cat */
        foreach ($responseData['items'] as $cat) {
            $category = new \stdClass();
            $category->name = $cat->getAttr('name');
            $category->icon = $cat->getAttr('icon');

            $points = new \stdClass();
            $pointsCount = 1;
            foreach ($cat->getChildren('category') as $child) {
                  if (!empty($child->getAttr('map'))) {
                    $coordinates = explode(',', $child->getAttr('map'));

                    $point = new \stdClass();
                    $point->name = $child->getAttr('name');
                    $point->address = $child->getAttr('address');
                    $point->coordinates = [$coordinates[0], $coordinates[1]];

                    $pointName = 'point'.$pointsCount++;
                    $points->{$pointName} = $point;
                }
            }

            $category->points = $points;

            $categoryName = 'category'.$objectsCount++;
            $objects->{$categoryName} = $category;
        }

        return $objects;
    }

    /**
     * @param int $configuration
     * @param int $typeId
     *
     * @return UnicatAttribute[]
     */
    public function getAttrsByType(int $configuration, int $typeId)
    {
        $ucm = $this->container->get('unicat')->getConfigurationManager($configuration);

        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->container->get('doctrine.orm.entity_manager');

        $itemType = $em->getRepository('UnicatModuleBundle:UnicatItemType')->find($typeId);

        $attrs = [];

        foreach ($itemType->getAttributesGroups() as $attributesGroup) {
            $tmp = $em->getRepository(UnicatAttribute::class)->findByGroupsNames($configuration, [$attributesGroup->getName()]);

            foreach ($tmp as $atr) {
                if (!$atr->isEnabled()) {
                    continue;
                }

                if ($atr->getIsDedicatedTable() or $atr->isType('unicat_item')) {
                    $attrs[] = $atr;
                }
            }
        }

        $attrsArray = [];
        /** @var UnicatAttribute $a */
        foreach ($attrs as $a) {
            if (empty($a->getSearchFormType())) {
                continue;
            }

            $params    = $a->getParams();
            $choices   = [];
            $max_value = null;
            $min_value = null;

            if ($a->isType('float') or $a->isType('integer')) {
                $responseData = $ucm->getData([
                    'type' => $itemType->getName(),
                    'criteria' => [
                        [$a->getName(), 'IS NOT NULL'],
                    ],
                    'order' => [
                        $a->getName() => 'ASC',
                    ],
                    'pager' => [1, 1],
                ]);

                if ($responseData['total_count'] > 0) {
                    /** @var ItemModel $item */
                    foreach ($responseData['items'] as $item) {
                        $min_value = $item->getAttr($a->getName());
                    }
                }

                $responseData = $ucm->getData([
                    'type' => $itemType->getName(),
                    'criteria' => [
                        [$a->getName(), 'IS NOT NULL'],
                    ],
                    'order' => [
                        $a->getName() => 'DESC',
                    ],
                    'pager' => [1, 1],
                ]);

                if ($responseData['total_count'] > 0) {
                    /** @var ItemModel $item */
                    foreach ($responseData['items'] as $item) {
                        $max_value = $item->getAttr($a->getName());
                    }
                }
            } elseif ($a->isType('unicat_item')) {
                $responseData = $ucm->getData([
                    'type' => $a->getItemsType()->getName(),
                    'pager' => [100, 1],
                ]);

                foreach ($responseData['items'] as $item) {
                    $params['form']['choices'][(string) $item] = $item->getId();
                }
            } elseif ($a->isType('choice')) {
                $choices = $ucm->getGroupedCountsQueryBuilder($a->getName(), [
                    'type' => $itemType->getName(),
                    'criteria' => [
                        [$a->getName(), 'IS NOT NULL'],
                    ],
                    'pager' => [200, 1],
                ])->getQuery()->getResult();
            }

            $attrsArray['item_'.$a->getId()] = [
                'name' => $a->getName(),
                'type' => $a->getType(),
                'entity_type' => 'item',
                'search_form_title' => $a->getSearchFormTitle(),
                'search_form_type' => $a->getSearchFormType(),
                'title' => $a->getTitle(),
                'description' => $a->getDescription(),
                'params' => $params,
                'max_value' => $max_value,
                'min_value' => $min_value,
                'choices' => $choices,
            ];
        }

        /** @var UnicatTaxonomy $t */
        foreach ($itemType->getTaxonomies() as $t) {
            $params = [];

            // @todo FIX IT!!! HEAVY HACK!!! for zbs
            if ($t->getName() == 'location') {
                $taxons = $ucm->getTaxonRepository()->findBy([
                    'taxonomy' => $t,
                    'parent'   => 3,
                ]);
            } else {
                $taxons = $ucm->getTaxonRepository()->findBy([
                    'taxonomy' => $t,
                ]);
            }

            foreach ($taxons as $taxon) {
                $params['form']['choices'][(string) $taxon] = $taxon->getId();
            }

            $attrsArray['taxonomy_'.$t->getId()] = [
                'name' => $t->getName(),
                'type' => null,
                'entity_type' => 'taxonomy',
                'search_form_title' => $t->getTitleForm(),
                'search_form_type' => 'multiselect',
                'title' => $t->getTitle(),
                'description' => null,
                'params' => $params,
                'max_value' => null,
                'min_value' => null,
                'choices' => null,
            ];
        }

        return $attrsArray;
    }
}

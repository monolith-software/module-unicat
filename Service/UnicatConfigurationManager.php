<?php

namespace Monolith\Module\Unicat\Service;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Monolith\Module\Unicat\Doctrine\UnicatEntityManager;
use Monolith\Module\Unicat\Pagination\UnicatORMAdapter;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Smart\CoreBundle\Pagerfanta\SimpleDoctrineORMAdapter;
use SmartCore\Bundle\MediaBundle\Service\CollectionService;
use Monolith\Module\Unicat\Entity\UnicatAttribute;
use Monolith\Module\Unicat\Entity\UnicatAttributesGroup;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Entity\UnicatItemType;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Monolith\Module\Unicat\Event\ItemUpdateEvent;
use Monolith\Module\Unicat\Form\Type\AttributeFormType;
use Monolith\Module\Unicat\Form\Type\AttributesGroupFormType;
use Monolith\Module\Unicat\Form\Type\ItemFormType;
use Monolith\Module\Unicat\Form\Type\TaxonomyFormType;
use Monolith\Module\Unicat\Form\Type\TaxonCreateFormType;
use Monolith\Module\Unicat\Form\Type\TaxonFormType;
use Monolith\Module\Unicat\Model\AbstractValueModel;
use Monolith\Module\Unicat\Model\ItemModel;
use Monolith\Module\Unicat\Model\ItemRepository;
use Monolith\Module\Unicat\Model\TaxonModel;
use Monolith\Module\Unicat\UnicatEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UnicatConfigurationManager
{
    /** @var \Doctrine\Common\Persistence\ManagerRegistry */
    protected $doctrine;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var UnicatEntityManager */
    protected $uem;

    /** @var  EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var \Symfony\Component\Form\FormFactoryInterface */
    protected $formFactory;

    /** @var \SmartCore\Bundle\MediaBundle\Service\CollectionService */
    protected $mc;

    /** @var \Monolith\Module\Unicat\Entity\UnicatConfiguration */
    protected $configuration;

    /** @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface */
    protected $securityToken;

    /**
     * @param ManagerRegistry $doctrine
     * @param FormFactoryInterface $formFactory
     * @param UnicatConfiguration $configuration
     * @param CollectionService $mc
     * @param TokenStorageInterface $securityToken
     */
    public function __construct(
        ManagerRegistry $doctrine,
        UnicatEntityManager $unicatEntityManager,
        FormFactoryInterface $formFactory,
        UnicatConfiguration $configuration,
        CollectionService $mc,
        TokenStorageInterface $securityToken,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->doctrine      = $doctrine;
        $this->em            = $doctrine->getManager();
        $this->uem           = $unicatEntityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->formFactory   = $formFactory;
        $this->mc            = $mc;
        $this->configuration = $configuration;
        $this->securityToken = $securityToken;
    }

    /**
     * @param array $options
     * @param bool  $is_dump_query
     *
     * @return QueryBuilder
     * @throws \Exception
     */
    public function buildQueryBuilder(array $options, bool $is_dump_query = false)
    {
        if (!isset($options['type']) or empty($options['type'])) {
            throw new \Exception('Missed required option "type".');
        }

        $itemType = $this->em->getRepository(UnicatItemType::class)->findOneBy([
            'name' => $options['type'],
            'configuration' => $this->configuration,
        ]);

        if (empty($itemType)) {
            throw new \Exception("Item type {$options['type']} is incorrect.");
        }

        $defaut  = [
            'is_deleted' => false, // true|'all' @todo
            'is_enabled' => true,  // false|'all'
            //'get_taxonomy' => true, // @todo
            // Пока вычисляется только по IN, можно подумать сделать ещё обработку NOT IN
            'taxonomy' => [
//                ['banks', 'OR', '2,25'],
            ],
            //'type' => 'jk', // Обязательное
            'criteria' => [
//                ['id', '>=', 300],
//                ['id', 'NOT IN', '412, 416'],
//                ['val', 'IS NULL']
//                ['val2', 'IS NOT NULL']
//                ['jk_type', '=', 1],
            ],
            'order' => [
//                'price' => 'ASC',
//                'id' => 'DESC',
            ],
            'pager' => [
                10, // items_per_page
                0, // page_num
            ],
        ];

        $options = $options + $defaut;

        // -----------------------


        $itemEntity = $this->configuration->getItemClass();

        $attributes = [];
        foreach ($this->getAttributes() as $name => $attribute) {
            if ($attribute->getIsEnabled()) {
                $attributes[$name] = $attribute;
            }
        }

        $from = $itemEntity.' i';

        $qb = $this->em->createQueryBuilder('i');
        $qb
            ->select('i')
            ->add('from', $from)
            ->where('i.type = '.$itemType->getId())
        ;

        if (is_bool($options['is_enabled'])) {
            $qb->andWhere('i.is_enabled = '.intval($options['is_enabled']));
        } elseif ($options['is_enabled'] !== 'all') {
            throw new \Exception('Wrong "is_enabled" parameter.');
        }

        $grouppedCriteria = [];

        foreach ($options['criteria'] as $criteria) {
            $grouppedCriteria[trim($criteria[0])][] = $criteria;
        }

        foreach ($grouppedCriteria as $field => $criterias) {
            // Выборка по полям сущности
            if ($field == 'id' or $field == 'position' or $field == 'slug') {
                foreach ($criterias as $key => $criteria) {
                    $comparison = trim($criteria[1]);

                    $val = '';
                    if (isset($criteria[2])) {
                        $val = $criteria[2];
                    }

                    if ($comparison == 'IN' or $comparison == 'NOT IN') {
                        if (is_array($val)) {
                            $val = implode(',', $val);
                        }

                        $qb->andWhere('i.'.$field.' '.$comparison.' ('.$val.')');
                    } else {
                        $qb->andWhere('i.'.$field.' '.$comparison.' :'.$field.$key)
                            ->setParameter($field.$key, $val);
                    }
                }
            } elseif (isset($attributes[$field])) {
                /** @var UnicatAttribute $attr */
                $attr = $attributes[$field];

                if ($attr->isDedicatedTable()) { // Выборка по значениям из внешних таблиц
                    $condition = '';
                    $firstCondition = true;
                    foreach ($criterias as $key => $criteria) {
                        $comparison = trim(strtoupper($criteria[1]));

                        if ($comparison == 'IS NOT NULL' or $comparison == 'IS NULL') {
                            if ($firstCondition) {
                                $condition = $field.".value {$comparison} ";
                                $firstCondition = false;
                            } else {
                                $condition .= ' AND '.$field.".value {$comparison} ";
                            }
                        } else {
                            $val = '';
                            if (isset($criteria[2])) {
                                $val = $criteria[2];
                            }

                            if (($comparison == 'IN' or $comparison == 'NOT IN') and empty($val)) {
                                continue;
                            }

                            // @todo сделать IN
                            if ($comparison == 'IN' or $comparison == 'NOT IN') {
                                if (is_array($val)) {
                                    $val = implode(',', $val);
                                }

                                $qb->join('i.attr_'.$field.'_value', $field.$key, 'WITH', $field.$key.".value {$comparison} ({$val})");
                            } else {
                                if ($firstCondition) {
                                    $condition = $field.".value {$comparison} :".$field.$key;
                                    $firstCondition = false;
                                } else {
                                    $condition .= ' AND '.$field.".value {$comparison} :".$field.$key;
                                }

                                $qb->setParameter($field.$key, $val);
                            }
                        }
                    }

                    $qb->join('i.attr_'.$field.'_value', $field, 'WITH', $condition);
                } elseif ($attr->isItemsTypeMany2many()) { // М2М связи
                    $comparison = trim($criterias[0][1]);

                    $val = '';
                    if (isset($criterias[0][2])) {
                        $val = $criterias[0][2];
                    }

                    if (($comparison == 'IN' or $comparison == 'NOT IN') and empty($val)) {
                        continue;
                    }

                    if ($comparison == 'IN' or $comparison == 'NOT IN') {
                        if (is_array($val)) {
                            $val = implode(',', $val);
                        }

                        $qb->join('i.attr_'.$field, $field, 'WITH', $field.".id {$comparison} ({$val})");
                    } else {
                        $qb->join('i.attr_'.$field, $field, 'WITH', $field.".id = :".$field)
                            ->setParameter($field, $val);
                    }
                } elseif ($attr->getType() == 'unicat_item') { // Одиночные связи
                    foreach ($criterias as $key => $criteria) {
                        if (count($criteria) < 2) {
                            continue;
                        }

                        $comparison = trim($criteria[1]);

                        $val = '';
                        if (isset($criteria[2])) {
                            $val = $criteria[2];
                        }

                        if (($comparison == 'IN' or $comparison == 'NOT IN') and empty($val)) {
                            continue;
                        }

                        if ($comparison == 'IN' or $comparison == 'NOT IN') {
                            if (is_array($val)) {
                                $val = implode(',', $val);
                            }

                            $qb->join('i.attr_'.$field, $field, 'WITH', $field.".id {$comparison} ({$val})");
                        } else {
//                            $qb->join('i.attr_'.$field, $field, 'WITH', $field.".id = :".$field)
//                                ->setParameter($field, $val);
                            $qb->andWhere('i.attr_'.$field.' '.$comparison.' :'.$field.$key)
                                ->setParameter($field.$key, $val);
                        }
                    }
                }
            }
        }

        $firstOrderBy = true;
        if (!empty($options['order'])) {
            foreach ($options['order'] as $field => $value) {
                if (isset($attributes[$field])) {
                    $attr = $attributes[$field];

                    if ($attr->isDedicatedTable()) {
                        if ($firstOrderBy) {
                            $qb->leftJoin('i.attr_'.$field.'_value', $field.'_order')->orderBy($field.'_order.value ', $value);
                            $firstOrderBy = false;
                        } else {
                            $qb->leftJoin('i.attr_'.$field.'_value', $field.'_order')->addOrderBy($field.'_order.value ', $value);
                        }
                    }
                }

                if ($field == 'id' or $field == 'position' or $field == 'slug') {
                    if ($firstOrderBy) {
                        $qb->orderBy("i.$field", $value);
                        $firstOrderBy = false;
                    } else {
                        $qb->addOrderBy("i.$field", $value);
                    }
                }
            }
        }

        // Таксоны

        $requestedTaxons = ['IN' => [], 'OR' => []];

        foreach ($options['taxonomy'] as $taxonomyCriteria) {
            if (!is_array($taxonomyCriteria) or count($taxonomyCriteria) != 3) {
                continue;
            }

            $taxonomy = $this->em->getRepository(UnicatTaxonomy::class)->findOneBy(['name' => $taxonomyCriteria[0]]);

            if (empty($taxonomy)) {
                throw new \Exception("Taxonomy name '{$taxonomy}' is incorrect.");
            }

            if (is_array($taxonomyCriteria[2])) {
                $values = $taxonomyCriteria[2];
            } else {
                $values = explode(',', $taxonomyCriteria[2]);
            }

            if ($taxonomyCriteria[1] == 'IN' or $taxonomyCriteria[1] == 'AND') {
                $comparison = 'IN';
            } elseif ($taxonomyCriteria[1] == 'OR') {
                $comparison = 'OR';
            }

            foreach ($values as $value) {
                $value = trim($value);
                if (intval($value)) {
                    $requestedTaxons[$comparison][$value] = $value;
                }
            }
        }

        if (count($requestedTaxons['IN']) or count($requestedTaxons['OR'])) {
            if (count($requestedTaxons['OR'])) {
                $oRcondition = '';

                $first = true;
                foreach ($requestedTaxons['OR'] as $val) {
                    if ($first) {
                        $oRcondition .= "t2.id = :taxon".$val;
                        $first = false;
                    } else {
                        $oRcondition .= " OR t2.id = :taxon".$val;
                    }

                    $qb->setParameter('taxon'.$val, $val);
                }

                $qb->join('i.taxons', 't2', 'WITH', $oRcondition);
            }

            if (count($requestedTaxons['IN'])) {
                $requestedTaxonsInStr = implode(',', $requestedTaxons['IN']);

                $qb->join('i.taxons', 't1', 'WITH', "t1.id IN ({$requestedTaxonsInStr})");
                if (count($requestedTaxons['IN']) > 1) {
                    $qb->groupBy('i.id')->having('COUNT(i.id) = '.count($requestedTaxons['IN']));
                }
            }
        }

        if ($is_dump_query) {
            dump($qb->getQuery()->getSQL(), $qb->getQuery());
        }

        return $qb;
    }

    /**
     * @param string $field
     * @param array  $options
     * @param bool   $is_dump_query
     *
     * @return QueryBuilder
     * @throws \Exception
     */
    public function getGroupedCountsQueryBuilder(string $field, array $options, bool $is_dump_query = false)
    {
        $qb = $this->buildQueryBuilder($options, false);

        // @todo !!! проверки на не пустой groupBy в $qb->getDQLParts()
        $qb
            ->select("COUNT($field.value) AS count, $field.value AS value")
            ->groupBy($field.'.value')
        ;

        if ($is_dump_query) {
            dump($qb->getQuery()->getSQL(), $qb->getQuery());
        }

        return $qb;
    }

    /**
     * @param array $options
     * @param bool  $is_dump_query
     * @param bool  $hydrateObject
     *
     * @return array|ItemModel[]
     *
     * @throws \Exception
     */
    public function getData(array $options, bool $is_dump_query = false, $hydrateObject = true)
    {
        $baseEm = $this->getEm();
        $this->setEm($this->uem);

        $qb = $this->buildQueryBuilder($options, $is_dump_query);

        if (isset($options['pager'][0]) and is_numeric($options['pager'][0]) and $options['pager'][0] > 0) {
            $limit = $options['pager'][0];
        } else {
            $limit = 0;
        }

        if (isset($options['pager'][1]) and is_numeric($options['pager'][1]) and $options['pager'][1] >= 0) {
            $offset = $options['pager'][1];
        } else {
            $offset = 0;
        }

//        $pagerfanta = new Pagerfanta(new SimpleDoctrineORMAdapter($qb->getQuery()));
        $pagerfanta = new Pagerfanta(new DoctrineORMAdapter($qb->getQuery()->setHydrationMode($hydrateObject ? AbstractQuery::HYDRATE_OBJECT : AbstractQuery::HYDRATE_ARRAY)));
//        $pagerfanta = new Pagerfanta(new UnicatORMAdapter($qb->getQuery()->setHydrationMode($hydrateObject ? AbstractQuery::HYDRATE_OBJECT : AbstractQuery::HYDRATE_ARRAY), false));

        if (!empty($limit)) {
            $pagerfanta->setMaxPerPage($limit);
        }

        if (!empty($offset)) {
            try {
                $pagerfanta->setCurrentPage($offset);
            } catch (NotValidCurrentPageException $e) {
                $pagerfanta->setCurrentPage(1);
            }
        }

        $data = [
            'have_to_paginate'  => $pagerfanta->haveToPaginate(),
            'current_page'      => $pagerfanta->getCurrentPage(),
            'max_per_page'      => $pagerfanta->getMaxPerPage(),
            'total_page'        => $pagerfanta->getNbPages(),
            'total_count'       => $pagerfanta->getNbResults(),
        ];

        if ($pagerfanta->hasPreviousPage()) {
            $data['previous_page'] = $pagerfanta->getPreviousPage();
        } else {
            $data['previous_page'] = 'NONE';
        }

        if ($pagerfanta->hasNextPage()) {
            $data['next_page'] = $pagerfanta->getNextPage();
        } else {
            $data['next_page'] = 'NONE';
        }

        $data['items'] = $pagerfanta;

        $this->setEm($baseEm);

        return $data;
    }

    /**
     * @param ItemModel $item
     * @param array     $requestArray
     *
     * @return array
     *
     * @todo глубину вложенности.
     */
    public function getItemDataAsArray(ItemModel $item, array $requestArray = [])
    {
        /** @var UnicatAttribute[] $attributes */
        $attributes = [];
        foreach ($this->getAttributes() as $name => $attribute) {
            if ($attribute->getIsEnabled()) {
                $attributes[$name] = $attribute;
            }
        }

        $data = [
            'id' => $item->getId(),
            'slug' => $item->getSlug(),
            'meta' => $item->getMeta(),
            'position' => $item->getPosition(),
            'type' => $item->getType()->getName(),
            'taxonomy' => [],
            'attrs' => [],
            'hidden_extra' => $item->getHiddenExtra(),
        ];

        // Сначала ищем связи unicat_item
        foreach ($attributes as $name => $attribute) {
            if ($attribute->isItemsTypeMany2many() and $item->hasAttribute($name)) {
                /** @var ItemModel $item2 */
                foreach ($item->getAttr($name) as $item2) {
                    $data['attrs'][$name][$item2->getId()] = $this->getItemDataAsArray($item2, $requestArray);
                }
            } elseif ($attribute->getType() == 'unicat_item' and $item->hasAttribute($name)) {
                $item2 = $item->getAttr($name);
                if ($item2 instanceof ItemModel) {
                    $data['attrs'][$name] = $this->getItemDataAsArray($item2, $requestArray);
                }
            }
        }

        // Потом всем остальные атрибуты
        foreach ($item->getAttributes() as $name2 => $val2) {
            if (isset($attributes[$name2]) and !is_null($val2)) {
                /** @var UnicatAttribute $attr */
                $attr = $attributes[$name2];

                if ($attr->getType() == 'choice') {
                    $val3 = [];
                    $params = $attr->getParam('form');

                    if (isset($params['choices'])) {
                        $params = array_flip($params['choices']);
                    }

                    $val3[$val2] = $params[$val2];
                    $val2 = $val3;
                }

                if ($attr->getType() == 'image') {
                    $mc = $this->getMediaCollection();

                    if (isset($requestArray['attr_params'][$item->getType()->getName()][$attr->getName()]['filter'])) {
                        $filter = $requestArray['attr_params'][$item->getType()->getName()][$attr->getName()]['filter'];
                    } else {
                        $filter = $attr->getParam('filter');
                    }

                    if (empty($filter)) {
                        $filter = null;
                    }

                    $val2 = $mc->get($val2, $filter);
                }

                $data['attrs'][$name2] = $val2;
            }
        }

        foreach ($item->getTaxons() as $taxon) {
            $data['taxonomy'][$taxon->getTaxonomy()->getName()][$taxon->getId()] = [
                'id' => $taxon->getId(),
                'parent' => $taxon->getParent(),
                'title' => $taxon->getTitle(),
                'slug' => $taxon->getSlug(),
                'slug_full' => $taxon->getSlugFull(),
                'attrs' => $taxon->getProperties(),
            ];
        }

        return $data;
    }

    /**
     * @return UnicatConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param string|int $val
     * @param bool $use_item_id_as_slug
     *
     * @return ItemModel|null
     */
    public function findItem($val, $use_item_id_as_slug = true)
    {
        if (empty($val)) {
            return null;
        }

        $key = 'slug';

        if ($use_item_id_as_slug and intval($val)) {
            $key = 'id';
        }

        return $this->em->getRepository($this->configuration->getItemClass())->findOneBy([$key => $val]);
    }

    /**
     * @param string $slug
     * @param UnicatTaxonomy $taxonomy
     *
     * @return TaxonModel[]
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function findTaxonsBySlug($slug = null, UnicatTaxonomy $taxonomy = null)
    {
        $taxons = [];
        $parent = null;
        foreach (explode('/', $slug) as $taxonName) {
            if (strlen($taxonName) == 0) {
                break;
            }

            /* @var TaxonModel $taxon */
            if ($taxonomy) {
                $taxon = $this->getTaxonRepository()->findOneBy([
                    'is_enabled' => true,
                    'parent'     => $parent,
                    'slug'       => $taxonName,
                    'taxonomy'   => $taxonomy,
                ]);
            } else {
                $taxon = $this->getTaxonRepository()->findOneBy([
                    'is_enabled' => true,
                    'parent'     => $parent,
                    'slug'       => $taxonName,
                ]);
            }

            if ($taxon) {
                $taxons[] = $taxon;
                $parent = $taxon;
            } else {
                throw new NotFoundHttpException();
            }
        }

        return $taxons;
    }

    /**
     * @return \Monolith\Module\Unicat\Model\TaxonRepository
     */
    public function getTaxonRepository()
    {
        return $this->em->getRepository($this->configuration->getTaxonClass());
    }

    /**
     * @return string
     */
    public function getTaxonClass()
    {
        return $this->configuration->getTaxonClass();
    }

    /**
     * @return ItemRepository
     */
    public function getItemRepository()
    {
        return $this->em->getRepository($this->configuration->getItemClass());
    }

    /**
     * @return UnicatTaxonomy
     */
    public function getDefaultTaxonomy()
    {
        return $this->configuration->getDefaultTaxonomy();
    }

    /**
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getAttributeCreateForm(array $options = [])
    {
        $attribute = new UnicatAttribute();
        $attribute->setUser($this->getUser());

        return $this->getAttributeForm($attribute, $options)
            ->add('create', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']])
        ;
    }

    /**
     * @param mixed $data    The initial data for the form
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getAttributeForm($data = null, array $options = [])
    {
        return $this->formFactory->create(AttributeFormType::class, $data, $options);
    }

    /**
     * @param UnicatAttribute $attribute
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getAttributeEditForm(UnicatAttribute $attribute, array $options = [])
    {
        $form = $this->getAttributeForm($attribute, $options)
            ->remove('name')
            ->remove('type')
            ->remove('items_type')
            //->remove('is_dedicated_table')
            ->remove('is_items_type_many2many')
            ->remove('update_all_records_with_default_value')
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
        ;

        $count = $this->em->getRepository($this->configuration->getItemClass())->count([]);
        if (empty($count)) {
            $form->add('delete', SubmitType::class, [
                'attr' => [
                    'class' => 'btn-danger',
                    'formnovalidate' => 'formnovalidate',
                    'onclick' => "return confirm('Вы уверены, что хотите удалить атрибут?')",
                ],
            ]);
        }

        $form->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);

        return $form;
    }

    /**
     * @param int $groupId
     *
     * @return UnicatAttributesGroup
     */
    public function getAttributesGroup($groupId)
    {
        return $this->em->getRepository(UnicatAttributesGroup::class)->find($groupId);
    }

    /**
     * @param TaxonModel $data
     * @param array      $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getTaxonForm(TaxonModel $data, array $options = [])
    {
        return $this->formFactory->create(TaxonFormType::class, $data, $options);
    }

    /**
     * @param UnicatTaxonomy $taxonomy
     * @param array           $options
     * @param TaxonModel|null $parent_taxon
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getTaxonCreateForm(UnicatTaxonomy $taxonomy, array $options = [], TaxonModel $parent_taxon = null)
    {
        $class = $this->configuration->getTaxonClass();
        /** @var TaxonModel $taxon */
        $taxon = new $class();
        $taxon
            ->setTaxonomy($taxonomy)
            ->setIsInheritance($taxonomy->getIsDefaultInheritance())
            ->setUser($this->getUser())
        ;

        if ($parent_taxon) {
            $taxon->setParent($parent_taxon);
        }

        return $this->formFactory->create(TaxonCreateFormType::class, $taxon, $options)
            ->add('create', SubmitType::class, [
                'attr' => ['class' => 'btn btn-success'],
            ]);
    }

    /**
     * @param TaxonModel $taxon
     * @param array      $options
     *
     * @return \Symfony\Component\Form\Form
     */
    public function getTaxonEditForm(TaxonModel $taxon, array $options = [])
    {
        return $this->getTaxonForm($taxon, $options)
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param int $id
     *
     * @return TaxonModel|null|object
     */
    public function getTaxon($id)
    {
        return $this->getTaxonRepository()->find($id);
    }

    /**
     * @param int $groupId
     *
     * @return UnicatAttribute[]
     */
    public function getAttribute($id)
    {
        return $this->em->getRepository(UnicatAttribute::class)->find($id);
    }

    /**
     * @param mixed $data    The initial data for the form
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getItemEditForm($data = null, array $options = [])
    {
        return $this->getItemForm($data, $options)
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('delete', SubmitType::class, ['attr' => ['class' => 'btn btn-danger', 'onclick' => "return confirm('Вы уверены, что хотите удалить запись?')"]])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param mixed $data    The initial data for the form
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getItemForm($data = null, array $options = [])
    {
        return $this->formFactory->create(ItemFormType::class, $data, $options);
    }

    /**
     * @param mixed $data    The initial data for the form
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getItemCreateForm($data = null, array $options = [])
    {
        return $this->getItemForm($data, $options)
            ->add('create', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getTaxonomyCreateForm(array $options = [])
    {
        $taxonomy = new UnicatTaxonomy();
        $taxonomy->setConfiguration($this->configuration);

        return $this->getTaxonomyForm($taxonomy, $options)
            ->add('create', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getTaxonomyEditForm($data = null, array $options = [])
    {
        return $this->getTaxonomyForm($data, $options)
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getAttributesGroupCreateForm(array $options = [])
    {
        $group = new UnicatAttributesGroup();
        $group->setConfiguration($this->configuration);

        return $this->getAttributesGroupForm($group, $options)
            ->add('create', SubmitType::class, ['attr' => ['class' => 'btn btn-success']])
            ->add('cancel', SubmitType::class, ['attr' => ['class' => 'btn-default', 'formnovalidate' => 'formnovalidate']]);
    }

    /**
     * @param mixed|null $data
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getTaxonomyForm($data = null, array $options = [])
    {
        return $this->formFactory->create(TaxonomyFormType::class, $data, $options);
    }

    /**
     * @param mixed|null $data
     * @param array $options
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    public function getAttributesGroupForm($data = null, array $options = [])
    {
        return $this->formFactory->create(AttributesGroupFormType::class, $data, $options);
    }

    /**
     * @param int $id
     *
     * @return UnicatTaxonomy
     */
    public function getTaxonomy($id)
    {
        return $this->em->getRepository(UnicatTaxonomy::class)->find($id);
    }

    /**
     * @return ItemModel
     */
    public function createItemEntity()
    {
        $class = $this->configuration->getItemClass();

        return new $class();
    }

    /**
     * @param FormInterface $form
     * @param Request $request
     *
     * @return $this
     *
     * @todo события
     */
    public function createItem(FormInterface $form, Request $request)
    {
        return $this->saveItem($form, $request);
    }

    /**
     * @param FormInterface $form
     * @param Request $request
     *
     * @return $this
     *
     * @todo события
     */
    public function updateItem(FormInterface $form, Request $request)
    {
        return $this->saveItem($form, $request);
    }

    /**
     * @param ItemModel $item
     *
     * @return $this
     *
     * @todo события
     */
    public function removeItem(ItemModel $item)
    {
        $item
            ->setTaxons([])
            ->setTaxonsSingle([])
        ;

        foreach ($this->getAttributes() as $attribute) {
            if ($attribute->isType('image') and $item->hasAttribute($attribute->getName())) {
                // @todo сделать кеширование при первом же вытаскивании данных о записи. тоже самое в saveItem(), а еще лучше выделить этот код в отельный защищенный метод.
                $tableItems = $this->em->getClassMetadata($this->configuration->getItemClass())->getTableName();
                $sql = "SELECT * FROM $tableItems WHERE id = '{$item->getId()}'";
                $res = $this->em->getConnection()->query($sql)->fetch();

                $fileId = null;
                if (!empty($res)) {
                    $previousAttributes = unserialize($res['attributes']);
                    $fileId = $previousAttributes[$attribute->getName()];
                }

                $this->mc->remove($fileId);
            }
        }

        $this->em->remove($item);
        $this->em->flush(); // Надо делать полный flush т.к. каскадом удаляются связи с таксонам. @todo убрать каскад remove

        return $this;
    }

    /**
     * @param FormInterface $form
     * @param Request $request
     *
     * @return $this|array
     *
     * @todo запаковать в транзакцию
     */
    public function saveItem(FormInterface $form, Request $request, bool $is_autoremove_image = true)
    {
        /** @var ItemModel $item */
        $item = $form->getData();

        $event = new ItemUpdateEvent($item);
        $this->eventDispatcher->dispatch(UnicatEvent::PRE_ITEM_UPDATE, $event);

        $groups = [];
        foreach ($item->getType()->getAttributesGroups() as $group) {
            $groups[] = $group->getName();
        }

        // Получение всех атрибутов типа записи.
        $attributes = $this->em->getRepository(UnicatAttribute::class)->findByGroupsNames($this->getConfiguration(), $groups);

        $item->setFirstParentAttributes([]);

        // Проверка и модификация атрибута. В частности загрука картинок и валидация.
        /** @var UnicatAttribute $attribute */
        foreach ($attributes as $attribute) {
            if ($attribute->isType('float')) {
                $data = str_replace(',', '.', $item->getAttribute($attribute->getName()));

                $item->setAttribute($attribute->getName(), $data);
            }

            if ($attribute->getIsDedicatedTable()) {
                continue;
            }

            if ($attribute->isType('image') and $item->hasAttribute($attribute->getName())) {
                // @todo Здесь выполняется нативный SQL т.к. ORM отдаёт скешированный - сделать через UoW.
                $tableItems = $this->em->getClassMetadata($this->configuration->getItemClass())->getTableName();
                $sql = "SELECT * FROM $tableItems WHERE id = '{$item->getId()}'";
                $res = $this->em->getConnection()->query($sql)->fetch();

                $fileId = null;

                if (!empty($res)) {
                    $previousAttributes = unserialize($res['attributes']);
                    if (isset($previousAttributes[$attribute->getName()])) {
                        $fileId = $previousAttributes[$attribute->getName()];
                    }
                }

                // удаление файла.
                $_delete_ = $request->request->get('_delete_');
                if (is_array($_delete_)
                    and isset($_delete_['attribute--'.$attribute->getName()])
                    and 'on' === $_delete_['attribute--'.$attribute->getName()]
                ) {
                    $this->mc->remove($fileId);
                    $fileId = null;
                } else {
                    $file = $item->getAttribute($attribute->getName());

                    if ($file instanceof File) {
                        if ($is_autoremove_image) {
                            $this->mc->remove($fileId);
                        }
                        $fileId = $this->mc->upload($file);
                    }
                }

                $item->setAttribute($attribute->getName(), $fileId);
            } elseif ($attribute->isType('gallery')) {
                $data = $item->getAttribute($attribute->getName());

                if (!is_array($data)) {
                    $data = json_decode($item->getAttribute($attribute->getName()), true);
                }

                $item->setAttribute($attribute->getName(), $data);
            } elseif ($attribute->isType('unicat_item')) {
                // Рекурсивное прописывание всех родителей.
                if (!$attribute->isItemsTypeMany2many()) {
                    $item->addFirstParentAttribute($attribute->getName());
                    $this->updateParentItems($item, $item);
                }
            }
        }

        // Удаление всех связей, чтобы потом просто назначить новые.
        $item
            ->setTaxons([])
            ->setTaxonsSingle([])
        ;

        $this->em->persist($item);
        $this->em->flush();

        // @todo если item уже существует, то сделать сохранение в один проход, но придумать как сделать обновление таксономии.

        // Вторым проходом обрабатываются атрибуты с внешних таблиц т.к. при создании новой записи нужно сгенерировать ID
        foreach ($attributes as $attribute) {
            if ($attribute->getIsDedicatedTable()) {
                $value = $item->getAttr($attribute->getName());

                $entityValueClass = $attribute->getValueClassNameWithNameSpace();

                /* @var AbstractValueModel $entityValue */
                // @todo пока допускается использование одного поля со значениями, но нужно предусмотреть и множественные.
                $entityValue = $this->em->getRepository($entityValueClass)->findOneBy(['item' => $item]);

                if (empty($entityValue)) {
                    if ($value === null) {
                        continue;
                    }

                    $entityValue= new $entityValueClass();
                    $entityValue->setItem($item);
                } elseif (!empty($entityValue) and $value === null) {
                    $this->em->remove($entityValue);
                    $this->em->flush();

                    continue;
                }

                if ($entityValue) {
                    $entityValue->setValue($value);

                    $this->em->persist($entityValue);
                    $this->em->flush();
                }
            } else {
                continue;
            }
        }

        $pd = $request->request->get($form->getName(), []);

        $taxons = [];
        foreach ($pd as $key => $val) {
            if (false !== strpos($key, 'taxonomy--')) {
                if (is_array($val)) {
                    foreach ($val as $val2) {
                        if (!empty($val2)) {
                            $taxons[] = $val2;
                        }
                    }
                } else {
                    if (!empty($val)) {
                        $taxons[] = $val;
                    }
                }
            }
        }

        //$request->request->set($form->getName(), $pd);
        //$taxonsCollection = $this->em->getRepository($this->getTaxonClass())->findIn($taxons);

        $taxons_ids = implode(',', $taxons);

        if (!empty($taxons_ids)) {
            // @todo убрать в Repository
            $taxonsSingle = $this->em->createQuery("
                SELECT c
                FROM {$this->getTaxonClass()} c
                WHERE c.id IN({$taxons_ids})
            ")->getResult();

            $item->setTaxonsSingle($taxonsSingle);

            $taxonsInherited = [];
            foreach ($taxonsSingle as $taxon) {
                $this->getTaxonsInherited($taxonsInherited, $taxon);
            }

            $item->setTaxons($taxonsInherited);
        }

        $this->em->persist($item);
        $this->em->flush();

        if ($item->getSlug() === null) {
            $item->setSlug($item->getId());

            $this->em->persist($item);
            $this->em->flush();
        }

        $event = new ItemUpdateEvent($item);
        $this->eventDispatcher->dispatch(UnicatEvent::POST_ITEM_UPDATE, $event);

        return $this;
    }

    /**
     * Рекурсивное обновление вложенных родительских связей.
     *
     * @param ItemModel $baseItem
     * @param ItemModel $item
     */
    protected function updateParentItems(ItemModel $baseItem, ItemModel $item)
    {
        foreach ($item->getParentItems() as $attr => $parentItem) {
            $baseItem->setAttribute(str_replace('attr_', '', $attr), $parentItem);
            $this->updateParentItems($baseItem, $parentItem);
        }
    }

    /**
     * @param UnicatItemType|null $itemType
     *
     * @return UnicatItemType[]
     */
    public function getChildrenTypes(UnicatItemType $itemType = null)
    {
        if (empty($itemType)) {
            return [];
        }

        $attrs = $this->em->getRepository(UnicatAttribute::class)->findBy(['items_type' => $itemType]);

        $attrGroups = [];
        foreach ($attrs as $attr) {
            foreach ($attr->getGroups() as $group) {
                $attrGroups[$group->getId()] = $group->getName();
            }
        }

        $itemTypes = [];
        foreach ($this->em->getRepository(UnicatItemType::class)->findAll() as $itemType2) {
            foreach ($itemType2->getAttributesGroups() as $attrGroup2) {
                if (isset($attrGroups[$attrGroup2->getId()]) and $itemType->getId() !== $itemType2->getId()) {
                    $itemTypes[$itemType2->getId()] = $itemType2;
                }
            }
        }

        return $itemTypes;
    }
    
    /**
     * Рекурсивный обход всех вложенных таксонов.
     *
     * @param array      $taxonsInherited
     * @param TaxonModel $taxon
     */
    protected function getTaxonsInherited(&$taxonsInherited, TaxonModel $taxon)
    {
        if ($taxon->getParent() and $taxon->getIsInheritance()) {
            $this->getTaxonsInherited($taxonsInherited, $taxon->getParent());
        }

        $taxonsInherited[$taxon->getId()] = $taxon;
    }

    /**
     * @param int $groupId
     *
     * @return UnicatAttribute[]
     *
     * @deprecated
     */
    public function getAttributes($groupId = null)
    {
        $filter = ($groupId) ? ['group' => $groupId] : [];

        $filter['configuration'] = $this->getConfiguration()->getId();

        $attrs = [];
        foreach ($this->em->getRepository(UnicatAttribute::class)->findBy($filter, ['position' => 'ASC']) as $attr) {
            $attrs[$attr->getName()] = $attr;
        }

        return $attrs;
    }

    /**
     * @param UnicatAttribute $entity
     *
     * @return $this
     */
    public function createAttribute(UnicatAttribute $entity)
    {
        $this->em->persist($entity);
        $this->em->flush($entity);

        return $this;
    }

    /**
     * @param TaxonModel $taxon
     *
     * @return $this
     */
    public function updateTaxon(TaxonModel $taxon)
    {
        $this->em->persist($taxon);
        $this->em->flush($taxon);

        return $this;
    }

    /**
     * @param UnicatAttribute $entity
     *
     * @return $this
     */
    public function updateAttribute(UnicatAttribute $entity)
    {
        $this->em->persist($entity);
        $this->em->flush($entity);

        return $this;
    }

    /**
     * @param UnicatAttributesGroup $entity
     *
     * @return $this
     */
    public function updateAttributesGroup(UnicatAttributesGroup $entity)
    {
        $this->em->persist($entity);
        $this->em->flush($entity);

        return $this;
    }

    /**
     * @param UnicatTaxonomy $entity
     *
     * @return $this
     */
    public function updateTaxonomy(UnicatTaxonomy $entity)
    {
        $this->em->persist($entity);
        $this->em->flush($entity);

        return $this;
    }

    /**
     * @return int
     */
    protected function getUser()
    {
        if (null === $token = $this->securityToken->getToken()) {
            return 0;
        }

        if (!is_object($user = $token->getUser())) {
            return 0;
        }

        return $user;
    }

    /**
     * @return \SmartCore\Bundle\MediaBundle\Service\CollectionService
     */
    public function getMediaCollection()
    {
        return $this->mc;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEm(): EntityManagerInterface
    {
        return $this->em;
    }

    /**
     * @param EntityManagerInterface $em
     *
     * @return $this
     */
    public function setEm(EntityManagerInterface $em)
    {
        $this->em = $em;

        return $this;
    }
}

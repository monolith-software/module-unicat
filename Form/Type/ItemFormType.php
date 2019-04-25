<?php

namespace Monolith\Module\Unicat\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityRepository;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use Smart\CoreBundle\Form\DataTransformer\HtmlTransformer;
use Smart\CoreBundle\Form\TypeResolverTtait;
use SmartCore\Bundle\SeoBundle\Form\Type\MetaFormType;
use Monolith\Module\Unicat\Entity\UnicatAttribute;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Form\Tree\TaxonTreeType;
use Monolith\Module\Unicat\Model\ItemModel;
use Monolith\Module\Unicat\Model\TaxonModel;
use Monolith\Module\Unicat\Service\UnicatService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemFormType extends AbstractType
{
    use TypeResolverTtait;

    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var UnicatConfiguration */
    protected $configuration;

    /** @var UnicatService  */
    protected $unicat;

    /**
     * @param ManagerRegistry $doctrine
     * @param UnicatService   $unicat
     */
    public function __construct(ManagerRegistry $doctrine, UnicatService $unicat)
    {
        $this->configuration = UnicatService::getCurrentConfigurationStatic();
        $this->doctrine      = $doctrine;
        $this->unicat        = $unicat;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var ItemModel $item */
        $item = $options['data'];

        $builder
            //->add('hidden_extra', HiddenType::class)
            ->add(
                $builder
                    ->create('hidden_extra', HiddenType::class)
                    ->addViewTransformer(new HtmlTransformer(false))
            )
            ->add('slug', null, ['attr' => ['autofocus' => 'autofocus']])
            ->add('is_enabled')
            ->add('position')
            ->add('meta', MetaFormType::class, ['label' => 'Meta tags'])
        ;

        foreach ($item->getType()->getTaxonomies() as $taxonomy) {
            $optionsCat = [
                'label'     => $taxonomy->getTitleForm(),
                'required'  => $taxonomy->getIsRequired(),
                'expanded'  => $taxonomy->isMultipleEntries(),
                'multiple'  => $taxonomy->isMultipleEntries(),
                'class'     => $this->configuration->getTaxonClass(),
            ];

            /** @var TaxonModel $taxon */
            foreach ($item->getTaxonsSingle() as $taxon) {
                if ($taxon->getTaxonomy()->getName() === $taxonomy->getName()) {
                    if ($taxonomy->isMultipleEntries()) {
                        $optionsCat['data'][] = $taxon;
                    } else {
                        $optionsCat['data'] = $taxon;

                        break;
                    }
                }
            }

            $optionsCat['unicat_taxonomy'] = $taxonomy;
            $builder->add('taxonomy--'.$taxonomy->getName(), TaxonTreeType::class, $optionsCat);
        }

        // @todo проверку на отсутствие групп
        $groups = [];
        foreach ($item->getType()->getAttributesGroups() as $attributesGroup) {
            $groups[] = $attributesGroup->getName();
        }

        if (empty($groups)) {
            return null;
        }

        foreach ($this->doctrine->getRepository(UnicatAttribute::class)->findByGroupsNames($this->configuration, $groups) as $attribute) {
            if ($attribute->isEnabled() == false) {
                continue;
            }

            $type = $attribute->getType();
            $propertyOptions = [
                'required'  => $attribute->getIsRequired(),
                'label'     => $attribute->getTitle(),
            ];

            $attributeOptions = array_merge($propertyOptions, $attribute->getParam('form'));

            foreach ($attribute->getGroups() as $group) {
                foreach ($group->getItemTypes() as $itemTypeInAttrGroup) {
                    if ($item->getType()->getId() == $itemTypeInAttrGroup->getId()) {
                        $attributeOptions['attr']['data-attr-group--'.$group->getName()] = $group->getTitle();
                    }
                }
            }

            if ($attribute->isType('image')) {
                // @todo сделать виджет загрузки картинок.
                //$type = 'genemu_jqueryimage';
                $type = AttributeImageFormType::class;

                if (isset($item)) {
                    $attributeOptions['data'] = $item->getAttribute($attribute->getName());
                    $attributeOptions['empty_data'] = $item->getAttribute($attribute->getName());
                }
            }

            if ($attribute->isType('file')) {
                //$type = AttributeFileFormType::class;
                $type = ElFinderType::class;
                $attributeOptions['instance'] = 'form';
                $attributeOptions['enable'] = true;
            }

            if ($attribute->isType('gallery')) {
                $type = AttributeGalleryFormType::class;
            }

            if ($attribute->isType('geomap')) {
                $type = AttributeGeomapFormType::class;
            }

            if ($attribute->isType('select')) {
                $type = ChoiceType::class;
            }

            if ($attribute->isType('datetime')) {
                $type = DateTimeType::class;
            }

            if ($attribute->isType('date')) {
                $type = DateType::class;
            }

            if ($attribute->isType('multiselect')) {
                $type = ChoiceType::class;
                $attributeOptions['expanded'] = true;
                //$propertyOptions['multiple'] = true; // @todo FS#407 продумать мультиселект
            }

            if (isset($attributeOptions['constraints'])) {
                $constraintsObjects = [];

                foreach ($attributeOptions['constraints'] as $constraintsList) {
                    foreach ($constraintsList as $constraintClass => $constraintParams) {
                        $_class = '\Symfony\Component\Validator\Constraints\\'.$constraintClass;

                        $constraintsObjects[] = new $_class($constraintParams);
                    }
                }

                $attributeOptions['constraints'] = $constraintsObjects;
            }

            if ($attribute->isType('unicat_item')) {
                if ($attribute->isItemsTypeMany2many()) {
                    $attributeOptions['expanded'] = true;
                    $attributeOptions['multiple'] = true;
                } else {
                    $attributeOptions['expanded'] = false;
                }

                $attributeOptions['class'] = get_class($item);
                $attributeOptions['query_builder'] = function (EntityRepository $er) use ($attribute) {
                    return $er->createQueryBuilder('e')
                        ->where('e.type = :type')
                        ->orderBy('e.position', 'ASC')
                        ->orderBy('e.id', 'ASC')
                        ->setParameter('type', $attribute->getItemsType())
                        ;
                };

                $builder->add('attr_'.$attribute->getName(), EntityType::class, $attributeOptions);

                continue;
            }

            if ($type == 'text') {
                $builder->add(
                    $builder
                        ->create('attribute--'.$attribute->getName(), TextType::class, $attributeOptions)
                        ->addViewTransformer(new HtmlTransformer(false))
                );
            } else {
                if ($type == 'choice_int') {
                    $type = 'choice';
                }

                $builder->add('attribute--'.$attribute->getName(), $this->resolveTypeName($type), $attributeOptions);
            }
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->configuration->getItemClass(),
        ]);
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'unicat_item_'.$this->configuration->getName();
    }
}

<?php

namespace Monolith\Module\Unicat\Form\Type;

use Doctrine\ORM\EntityRepository;
use Monolith\Module\Unicat\Entity\UnicatAttributesGroup;
use Monolith\Module\Unicat\Entity\UnicatItemType;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Monolith\Module\Unicat\Service\UnicatService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title',  null, ['attr'  => ['autofocus' => 'autofocus', 'placeholder' => 'Произвольная строка']])
            ->add('name',   null, ['attr' => ['placeholder' => 'Латинские буквы в нижем регистре.']])
            ->add('position')
            ->add('to_string_pattern')
            ->add('attributes_groups', EntityType::class, [
                'expanded' => true,
                'multiple' => true,
                'class'         => UnicatAttributesGroup::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->where('e.configuration = :configuration')
                        ->setParameter('configuration', UnicatService::getCurrentConfigurationStatic());
                },
                'required' => false,
            ])
            ->add('taxonomies', null, [
                'expanded' => true,
                'multiple' => true,
                'class'         => UnicatTaxonomy::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->where('e.configuration = :configuration')
                        ->setParameter('configuration', UnicatService::getCurrentConfigurationStatic());
                },
                'required' => false,
            ])
            ->add('content_min_width')
            ->add('order_by_attr')
            ->add('order_by_direction')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnicatItemType::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_item_type';
    }
}

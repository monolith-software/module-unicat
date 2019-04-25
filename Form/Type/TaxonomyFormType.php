<?php

namespace Monolith\Module\Unicat\Form\Type;

use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonomyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title',      null, ['attr'  => ['autofocus' => 'autofocus']])
            ->add('title_form', null, ['label' => 'Title in forms'])
            ->add('name')
            ->add('is_multiple_entries')
            ->add('is_required')
            ->add('is_default_inheritance', null, ['required' => false])
            ->add('is_tree',    null, ['required' => false])
            ->add('is_show_in_admin')
            ->add('position')
            ->add('properties')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnicatTaxonomy::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_taxonomy';
    }
}

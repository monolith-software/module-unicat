<?php

namespace Monolith\Module\Unicat\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;

class TaxonCreateFormType extends TaxonFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->remove('is_enabled')
            ->remove('is_inheritance')
            ->remove('meta')
            ->remove('properties')
        ;
    }

    public function getBlockPrefix()
    {
        return 'unicat_taxon_'.$this->configuration->getName().'_create';
    }
}

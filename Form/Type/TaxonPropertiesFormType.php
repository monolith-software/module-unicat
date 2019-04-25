<?php

namespace Monolith\Module\Unicat\Form\Type;

use Smart\CoreBundle\Form\TypeResolverTtait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonPropertiesFormType extends AbstractType
{
    use TypeResolverTtait;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'properties'  => [],
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        foreach ($options['properties'] as $name => $opt) {
            if ('image' === $opt) {
                $type = AttributeImageFormType::class;
            } elseif (isset($opt['type'])) {
                $type = $this->resolveTypeName($opt['type']);
            }

            if (is_array($opt)) {
                if (isset($opt['type'])) {
                    unset($opt['type']);
                }
            } else {
                $opt = [];
            }

            $builder->add($name, $type, $opt);
        }
    }

    public function getBlockPrefix()
    {
        return 'unicat_taxon_properties';
    }
}

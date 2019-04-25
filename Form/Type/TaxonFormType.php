<?php

namespace Monolith\Module\Unicat\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use SmartCore\Bundle\SeoBundle\Form\Type\MetaFormType;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Monolith\Module\Unicat\Form\Tree\TaxonTreeType;
use Monolith\Module\Unicat\Model\TaxonModel;
use Monolith\Module\Unicat\Service\UnicatService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Yaml\Yaml;

class TaxonFormType extends AbstractType
{
    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var UnicatConfiguration */
    protected $configuration;

    /** @param ManagerRegistry $doctrine */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->configuration = UnicatService::getCurrentConfigurationStatic();
        $this->doctrine      = $doctrine;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var TaxonModel $taxon */
        $taxon = $options['data'];

        $builder
            ->add('is_enabled',     null, ['required' => false])
            ->add('title',          null, ['attr' => ['autofocus' => 'autofocus']])
            ->add('slug')
            ->add('is_inheritance', null, ['required' => false])
            ->add('position')
            ->add('parent', TaxonTreeType::class, [
                'unicat_taxonomy' => $taxon->getTaxonomy(),
            ])
            ->add('meta', MetaFormType::class, ['label' => 'Meta tags'])
        ;

        if (!$taxon->getTaxonomy()->isTree()) {
            $builder->remove('parent');
        }

        $taxonomy = null;

        if (is_object($taxon) and $taxon->getTaxonomy() instanceof UnicatTaxonomy) {
            $taxonomy = $taxon->getTaxonomy();
        }

        if ($taxonomy) {
            $properties = Yaml::parse($taxonomy->getProperties());

            if (is_array($properties)) {
                $builder->add(
                    $builder->create('properties', TaxonPropertiesFormType::class,[
                        'required'   => false,
                        'properties' => $properties,
                    ])
                );
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => $this->configuration->getTaxonClass(),
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_taxon_'.$this->configuration->getName();
    }
}

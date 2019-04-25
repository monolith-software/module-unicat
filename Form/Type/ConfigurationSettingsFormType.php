<?php

namespace Monolith\Module\Unicat\Form\Type;

use Doctrine\ORM\EntityRepository;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurationSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var UnicatConfiguration $configuration */
        $configuration = $options['data'];

        $builder
            ->add('title',          null, ['attr'  => ['autofocus' => 'autofocus']])
            ->add('is_inheritance', null, ['required' => false])
            ->add('items_per_page')
            ->add('media_collection')
            ->add('default_taxonomy', EntityType::class, [
                'class' => UnicatTaxonomy::class,
                'query_builder' => function (EntityRepository $er) use ($configuration) {
                    return $er->createQueryBuilder('s')
                        ->where('s.configuration = :configuration')
                        ->setParameter('configuration', $configuration)
                    ;
                },
                'required' => false,
            ])
            ->add('icon')
            ->add('update', SubmitType::class, ['attr' => ['class' => 'btn btn-primary']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnicatConfiguration::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_configuration_settings';
    }
}

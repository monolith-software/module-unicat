<?php

namespace Monolith\Module\Unicat\Form\Type;

use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', null, ['attr' => ['autofocus' => 'autofocus', 'placeholder' => 'Произвольная строка']])
            ->add('name',  null, ['attr' => ['placeholder' => 'Латинские буквы в нижем регистре.']])
            ->add('media_collection')
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
        return 'unicat_configuration';
    }
}

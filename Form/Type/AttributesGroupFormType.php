<?php

namespace Monolith\Module\Unicat\Form\Type;

use Monolith\Module\Unicat\Entity\UnicatAttributesGroup;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Service\UnicatService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributesGroupFormType extends AbstractType
{
    /** @var UnicatConfiguration */
    protected $configuration;

    public function __construct()
    {
        $this->configuration = UnicatService::getCurrentConfigurationStatic();
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', null, ['attr' => ['autofocus' => 'autofocus', 'placeholder' => 'Произвольная строка']])
            ->add('name',  null, ['attr' => ['placeholder' => 'Латинские буквы в нижем регистре.']])
            ->add('position')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnicatAttributesGroup::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_attributes_group';
    }
}

<?php

namespace Monolith\Module\Unicat\Form\Type;

use Monolith\Bundle\CMSBundle\Module\AbstractNodePropertiesFormType;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Entity\UnicatItemType;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class NodePropertiesFormType extends AbstractNodePropertiesFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $configurations = [];
        foreach ($this->em->getRepository(UnicatConfiguration::class)->findAll() as $configuration) {
            $configurations[(string) $configuration] = $configuration->getId();
        }

        $types = [];
        foreach ($this->em->getRepository(UnicatItemType::class)->findAll() as $type) {
            $types[$type->getConfiguration()->getTitle().' -> '.$type->getTitle() ] = $type->getId();
        }

//        $finder = new Finder();
//        $finder->files()->sortByName()->depth('== 0')->name('*.html.twig')->in($this->kernel->getBundle('SiteBundle')->getPath().'/Resources/views/');

        $builder
            ->add('configuration_id', ChoiceType::class, [
                'choices'  => $configurations,
                'label'    => 'Configuration @deprecated',
                'required' => false,
            ])
            ->add('items_type_id', ChoiceType::class, [
                'choices'  => $types,
                'label'    => 'Items type',
                'required' => false,
            ])
            ->add('use_item_id_as_slug', CheckboxType::class, [
                'label'    => 'Использовать ID записей в качестве URI',
                'required' => false,
            ])
            ->add('params',     TextareaType::class, ['required' => false, 'attr' => ['cols' => 15, 'style' => 'height: 150px;'], 'label' => 'Params yaml'])
//            ->add('order_by',  null, ['required' => false])
//            ->add('order_dir', null, ['required' => false])
        ;
    }

    public static function getTemplate()
    {
        return '@UnicatModule/node_properties_form.html.twig';
    }

    public function getBlockPrefix()
    {
        return 'monolith_unicat_node_properties';
    }
}

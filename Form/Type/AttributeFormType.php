<?php

namespace Monolith\Module\Unicat\Form\Type;

use Doctrine\ORM\EntityRepository;
use Monolith\Module\Unicat\Entity\UnicatAttribute;
use Monolith\Module\Unicat\Entity\UnicatAttributesGroup;
use Monolith\Module\Unicat\Entity\UnicatConfiguration;
use Monolith\Module\Unicat\Entity\UnicatItemType;
use Monolith\Module\Unicat\Service\UnicatService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributeFormType extends AbstractType
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
            ->add('name',  null, ['attr' => ['placeholder' => 'Латинские буквы в нижем регистре и символы подчеркивания.']])
            ->add('is_dedicated_table', null, ['required' => false])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Text'        => 'text',
                    'Textarea'    => 'textarea',
                    'Integer'     => 'integer',
                    'Float'       => 'float',
                    'Email'       => 'email',
                    'URL'         => 'url',
                    'Date'        => 'date',
                    'Datetime'    => 'datetime',
                    'Checkbox'    => 'checkbox',
                    'Image'       => 'image',
                    'File'        => 'file',
                    'Gallery'     => 'gallery',
                    'Geomap'      => 'geomap',
                    'Choice'      => 'choice',
                    'Choice (Int Keys)' => 'choice_int',
                    'Multiselect' => 'multiselect',
                    'Unicat Item' => 'unicat_item',
                ],
            ])
            ->add('search_form_type', ChoiceType::class, [
                'choices' => [
                    'Text'          => 'text',
                    'Slider'        => 'slider',
                    'Slider range'  => 'slider_range',
                    'Multiselect'   => 'multiselect',
                    'Radio'         => 'radio',
                    'Checkbox'      => 'checkbox',
                ],
                'required' => false,
            ])
            ->add('search_form_title',  null, ['attr' => ['placeholder' => 'Заголовок поисковой формы.']])
            ->add('items_type', EntityType::class, [
                'class'         => UnicatItemType::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->where('e.configuration = :configuration')
                        ->orderBy('e.position')
                        ->setParameter('configuration', UnicatService::getCurrentConfigurationStatic());
                },
                'required' => false,
            ])
            ->add('is_items_type_many2many',    null, ['required' => false])
            ->add('description')
            ->add('params_yaml',   null, ['attr' => ['data-editor' => 'yaml']])
            ->add('groups', EntityType::class, [
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
            ->add('position')
            ->add('update_all_records_with_default_value', TextType::class, [
                'attr'     => ['placeholder' => 'Пустое поле - не обновлять записи'],
                'required' => false,
            ])
            ->add('is_enabled',    null, ['required' => false])
            ->add('is_primary',    null, ['required' => false])
            ->add('is_link',       null, ['required' => false])
            ->add('is_required',   null, ['required' => false])
            ->add('is_show_title', null, ['required' => false])
            ->add('show_in_admin', null, ['required' => false])
            ->add('show_in_list',  null, ['required' => false])
            ->add('show_in_view',  null, ['required' => false])
            ->add('open_tag')
            ->add('close_tag')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnicatAttribute::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'unicat_attribute';
    }
}

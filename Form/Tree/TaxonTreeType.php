<?php

namespace Monolith\Module\Unicat\Form\Tree;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader;
use Symfony\Bridge\Doctrine\Form\Type\DoctrineType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonTreeType extends DoctrineType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $choiceLoader = function (Options $options) {
            if (null === $options['choices']) {
                $qbParts = null;

                if (null !== $options['query_builder']) {
                    $entityLoader = $this->getLoader($options['em'], $options['query_builder'], $options['class']);
                } else {
                    $queryBuilder = $options['em']->getRepository($options['class'])->createQueryBuilder('e');
                    $entityLoader = $this->getLoader($options['em'], $queryBuilder, $options['class']);
                }

                $entityLoader->setTaxonomy($options['unicat_taxonomy']);

                $doctrineChoiceLoader = new DoctrineChoiceLoader(
                    $options['em'],
                    $options['class'],
                    $options['id_reader'],
                    $entityLoader
                );

                return $doctrineChoiceLoader;
            }
        };

        $resolver->setDefaults([
            'choice_label'  => 'form_title',
            'class'         => function (Options $options) {
                return $options['unicat_taxonomy']->getConfiguration()->getTaxonClass();
            },
            'choice_loader' => $choiceLoader,
            'required'      => false,
            'unicat_taxonomy' => null, // Monolith\Module\Unicat\Entity\UnicatTaxonomy
        ]);
    }

    public function getLoader(ObjectManager $manager, $queryBuilder, $class)
    {
        return new TaxonLoader($manager, $queryBuilder, $class);
    }

    public function getBlockPrefix()
    {
        return 'unicat_taxon_tree';
    }
}

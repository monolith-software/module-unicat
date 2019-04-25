<?php

namespace Monolith\Module\Unicat\Form\Tree;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityRepository;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Monolith\Module\Unicat\Model\TaxonModel;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;

class TaxonLoader implements EntityLoaderInterface
{
    /** @var EntityRepository */
    private $repo;

    /** @var TaxonModel[] */
    protected $result;

    /** @var int */
    protected $level;

    /** @var UnicatTaxonomy */
    protected $taxonomy;

    /**
     * @param ObjectManager $em
     * @param null $manager
     * @param null $class
     */
    public function __construct(ObjectManager $em, $manager, $class = null)
    {
        $this->repo = $em->getRepository($class);
    }

    /**
     * @param UnicatTaxonomy $taxonomy
     *
     * @return $this
     */
    public function setTaxonomy($taxonomy)
    {
        $this->taxonomy = $taxonomy;

        return $this;
    }

    /**
     * Returns an array of entities that are valid choices in the corresponding choice list.
     *
     * @return TaxonModel[] The entities.
     */
    public function getEntities()
    {
        $this->result = [];
        $this->level = 0;

        $this->addChild();

        return $this->result;
    }

    /**
     * @param TaxonModel|null $parent
     */
    protected function addChild(TaxonModel $parent = null)
    {
        $level = $this->level;
        $ident = '';
        while ($level--) {
            $ident .= '&nbsp;&nbsp;';
        }

        $this->level++;

        $taxons = $this->repo->findBy([
            'is_enabled' => true,
            'parent'     => $parent,
            'taxonomy'   => $this->taxonomy,
        ], ['position' => 'ASC']);

        /** @var $taxon TaxonModel */
        foreach ($taxons as $taxon) {
            $taxon->setFormTitle($ident.$taxon->getTitle());
            $this->result[] = $taxon;
            $this->addChild($taxon);
        }

        $this->level--;
    }

    /**
     * Returns an array of entities matching the given identifiers.
     *
     * @param string $identifier The identifier field of the object. This method
     *                           is not applicable for fields with multiple
     *                           identifiers.
     * @param array $values The values of the identifiers.
     *
     * @return array The entities.
     */
    public function getEntitiesByIds($identifier, array $values)
    {
        return $this->repo->findBy([$identifier => $values]);
    }
}

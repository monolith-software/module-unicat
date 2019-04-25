<?php

namespace Monolith\Module\Unicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity()
 * @ORM\Table(name="unicat__attributes_groups",
 *      indexes={
 *          @ORM\Index(columns={"position"}),
 *      },
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(columns={"name", "configuration_id"}),
 *      },
 * )
 *
 * @UniqueEntity(fields={"name", "configuration"}, message="Имя должно быть уникальным.")
 */
class UnicatAttributesGroup
{
    use ColumnTrait\Id;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\Name;
    use ColumnTrait\Position;
    use ColumnTrait\TitleNotBlank;

    /**
     * @var UnicatAttribute[]
     *
     * @ORM\ManyToMany(targetEntity="UnicatAttribute", mappedBy="groups")
     */
    protected $attributes;

    /**
     * @var UnicatItemType[]
     *
     * @ORM\ManyToMany(targetEntity="UnicatItemType", mappedBy="attributes_groups")
     */
    protected $item_types;

    /**
     * @todo подумать о привязке групп атрибутов к таксону
     *
     * @var TaxonModel
     *
     * ORM\ManyToOne(targetEntity="Taxon")
     **/
    //protected $taxon;

    /**
     * @var UnicatConfiguration
     *
     * @ORM\ManyToOne(targetEntity="UnicatConfiguration", inversedBy="attributes_groups")
     **/
    protected $configuration;

    /**
     * UnicatAttributesGroup constructor.
     */
    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->attributes = new ArrayCollection();
        $this->position   = 0;
    }

    /**
     * @see getName
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getTitle();
    }

    /**
     * @param UnicatAttribute[] $attributes
     *
     * @return $this
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @return UnicatAttribute[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param UnicatConfiguration $configuration
     *
     * @return $this
     */
    public function setConfiguration(UnicatConfiguration $configuration)
    {
        $this->configuration = $configuration;

        return $this;
    }

    /**
     * @return UnicatConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @return UnicatItemType[]
     */
    public function getItemTypes()
    {
        return $this->item_types;
    }

    /**
     * @param mixed $item_types
     *
     * @return $this
     */
    public function setItemTypes($item_types)
    {
        $this->item_types = $item_types;

        return $this;
    }
}

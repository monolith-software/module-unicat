<?php

namespace Monolith\Module\Unicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;

/**
 * @ORM\Entity()
 * @ORM\Table(name="unicat__items_types",
 *      indexes={
 *          @ORM\Index(columns={"name"}),
 *      }
 * )
 */
class UnicatItemType
{
    use ColumnTrait\Id;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\Name;
    use ColumnTrait\Position;
    use ColumnTrait\TitleNotBlank;
    use ColumnTrait\FosUser;

    /**
     * @var integer|null
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $content_min_width;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $order_by_attr;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $order_by_direction;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $to_string_pattern;

    /**
     * @var UnicatConfiguration
     *
     * @ORM\ManyToOne(targetEntity="UnicatConfiguration", inversedBy="item_types")
     */
    protected $configuration;

    /**
     * @var UnicatAttributesGroup[]|ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="UnicatAttributesGroup", inversedBy="item_types", cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\JoinTable(name="unicat__items_types_attributes_groups_relations",
     *      joinColumns={@ORM\JoinColumn(name="attribute_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="group_id", referencedColumnName="id")}
     * )
     */
    protected $attributes_groups;

    /**
     * @var UnicatTaxonomy[]|ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="UnicatTaxonomy", inversedBy="item_types", cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\JoinTable(name="unicat__items_types_taxonomies_relations",
     *      joinColumns={@ORM\JoinColumn(name="attribute_id", referencedColumnName="id")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="taxonomy_id", referencedColumnName="id")}
     * )
     * @ORM\OrderBy({"position" = "ASC", "id" = "ASC"})
     */
    protected $taxonomies;

    /**
     * UnicatItemType constructor.
     */
    public function __construct()
    {
        $this->attributes_groups = new ArrayCollection();
        $this->content_min_width = null;
        $this->created_at        = new \DateTime();
        $this->position          = 0;
        $this->taxonomies        = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getTitle();
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasAttribute($name)
    {
        foreach ($this->getAttributesGroups() as $group) {
            foreach ($group->getAttributes() as $attribute) {
                if ($attribute->getName() == $name) {
                    return true;
                }
            }
        }

        return false;
    }
    
    /**
     * @return ArrayCollection|UnicatAttributesGroup[]
     */
    public function getAttributesGroups()
    {
        return $this->attributes_groups;
    }

    /**
     * @param ArrayCollection|UnicatAttributesGroup[] $attributes_groups
     *
     * @return $this
     */
    public function setAttributesGroups($attributes_groups)
    {
        $this->attributes_groups = $attributes_groups;

        return $this;
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
     * @return ArrayCollection|UnicatTaxonomy[]
     */
    public function getTaxonomies()
    {
        return $this->taxonomies;
    }

    /**
     * @param ArrayCollection|UnicatTaxonomy[] $taxonomies
     *
     * @return $this
     */
    public function setTaxonomies($taxonomies)
    {
        $this->taxonomies = $taxonomies;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getToStringPattern()
    {
        return $this->to_string_pattern;
    }

    /**
     * @param null|string $to_string_pattern
     *
     * @return $this
     */
    public function setToStringPattern($to_string_pattern)
    {
        $this->to_string_pattern = $to_string_pattern;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getOrderByAttr()
    {
        return $this->order_by_attr;
    }

    /**
     * @param null|string $order_by_attr
     *
     * @return $this
     */
    public function setOrderByAttr($order_by_attr)
    {
        $this->order_by_attr = $order_by_attr;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getOrderByDirection()
    {
        return $this->order_by_direction;
    }

    /**
     * @param null|string $order_by_direction
     *
     * @return $this
     */
    public function setOrderByDirection($order_by_direction)
    {
        $this->order_by_direction = $order_by_direction;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getContentMinWidth()
    {
        return $this->content_min_width;
    }

    /**
     * @param int|null $content_min_width
     *
     * @return $this
     */
    public function setContentMinWidth($content_min_width)
    {
        $this->content_min_width = $content_min_width;

        return $this;
    }
}

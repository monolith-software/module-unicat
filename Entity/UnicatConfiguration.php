<?php

namespace Monolith\Module\Unicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
use SmartCore\Bundle\MediaBundle\Entity\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity()
 * @ORM\Table(name="unicat__configurations",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(columns={"title"}),
 *      }
 * )
 * @UniqueEntity(fields={"name"}, message="Конфигурация с таким именем уже существует.")
 * @UniqueEntity(fields={"title"}, message="Конфигурация с таким заголовком уже существует.")
 */
class UnicatConfiguration
{
    use ColumnTrait\Id;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\NameUnique;
    use ColumnTrait\TitleNotBlank;
    use ColumnTrait\FosUser;

    /**
     * Пространство имен сущностей, например: DemoSiteBundle\Entity\Unicat\Catalog\.
     *
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $entities_namespace;

    /**
     * Включает записи вложенных категорий.
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default":1})
     */
    protected $is_inheritance;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint", options={"default":10, "unsigned"=true})
     */
    protected $items_per_page;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $icon;

    /**
     * @var Collection
     *
     * @ORM\ManyToOne(targetEntity="SmartCore\Bundle\MediaBundle\Entity\Collection")
     */
    protected $media_collection;

    /**
     * @var UnicatTaxonomy|null
     *
     * @ORM\ManyToOne(targetEntity="UnicatTaxonomy")
     */
    protected $default_taxonomy;

    /**
     * @var UnicatAttribute[]
     *
     * @ORM\OneToMany(targetEntity="UnicatAttribute", mappedBy="configuration")
     * @ORM\OrderBy({"position" = "ASC"})
     */
    protected $attributes;

    /**
     * @var UnicatAttributesGroup[]
     *
     * @ORM\OneToMany(targetEntity="UnicatAttributesGroup", mappedBy="configuration")
     */
    protected $attributes_groups;

    /**
     * @var UnicatItemType[]
     *
     * @ORM\OneToMany(targetEntity="UnicatItemType", mappedBy="configuration")
     * @ORM\OrderBy({"position" = "ASC"})
     */
    protected $item_types;

    /**
     * @var UnicatTaxonomy[]
     *
     * @ORM\OneToMany(targetEntity="UnicatTaxonomy", mappedBy="configuration")
     */
    protected $taxonomies;

    /**
     * UnicatConfiguration constructor.
     */
    public function __construct()
    {
        $this->created_at           = new \DateTime();
        $this->is_inheritance       = true;
        $this->items_per_page       = 10;
        $this->entities_namespace   = null;
        $this->item_types           = new ArrayCollection();
        $this->taxonomies           = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getTaxonClass()
    {
        return $this->entities_namespace.'Taxon';
    }

    /**
     * @return string
     */
    public function getItemClass()
    {
        return $this->entities_namespace.'Item';
    }

    /**
     * @param string $entities_namespace
     *
     * @return $this
     */
    public function setEntitiesNamespace($entities_namespace)
    {
        $this->entities_namespace = $entities_namespace;

        return $this;
    }

    /**
     * @return string
     */
    public function getEntitiesNamespace()
    {
        return $this->entities_namespace;
    }

    /**
     * @param bool $is_inheritance
     *
     * @return $this
     */
    public function setIsInheritance($is_inheritance)
    {
        $this->is_inheritance = $is_inheritance;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIsInheritance()
    {
        return $this->is_inheritance;
    }

    /**
     * @return bool
     */
    public function isInheritance()
    {
        return $this->is_inheritance;
    }

    /**
     * @param Collection|null $media_collection
     *
     * @return $this
     */
    public function setMediaCollection(Collection $media_collection = null)
    {
        $this->media_collection = $media_collection;

        return $this;
    }

    /**
     * @return Collection|null
     */
    public function getMediaCollection()
    {
        return $this->media_collection;
    }

    /**
     * @return UnicatAttribute[]
     */
    public function getAttributes()
    {
        return $this->attributes;
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
     * @return UnicatItemType[]|ArrayCollection
     */
    public function getItemTypes()
    {
        return $this->item_types;
    }

    /**
     * @param UnicatItemType[] $item_types
     *
     * @return $this
     */
    public function setItemTypes($item_types)
    {
        $this->item_types = $item_types;

        return $this;
    }

    /**
     * @return UnicatAttributesGroup[]
     */
    public function getAttributesGroups()
    {
        return $this->attributes_groups;
    }

    /**
     * @param UnicatAttributesGroup[] $attributes_groups
     *
     * @return $this
     */
    public function setAttributesGroups($attributes_groups)
    {
        $this->attributes_groups = $attributes_groups;

        return $this;
    }

    /**
     * @return UnicatTaxonomy[]
     */
    public function getTaxonomies()
    {
        return $this->taxonomies;
    }

    /**
     * @param UnicatTaxonomy[]|ArrayCollection $taxonomies
     *
     * @return $this
     */
    public function setTaxonomies($taxonomies)
    {
        $this->taxonomies = $taxonomies;

        return $this;
    }

    /**
     * @return UnicatTaxonomy
     */
    public function getDefaultTaxonomy()
    {
        return $this->default_taxonomy;
    }

    /**
     * @param UnicatTaxonomy $default_taxonomy
     *
     * @return $this
     */
    public function setDefaultTaxonomy(UnicatTaxonomy $default_taxonomy = null)
    {
        $this->default_taxonomy = $default_taxonomy;

        return $this;
    }

    /**
     * @return int
     */
    public function getItemsPerPage()
    {
        return $this->items_per_page;
    }

    /**
     * @param int $items_per_page
     *
     * @return $this
     */
    public function setItemsPerPage($items_per_page)
    {
        if ($items_per_page < 1) {
            $items_per_page = 10;
        }

        $this->items_per_page = $items_per_page;

        return $this;
    }

    /**
     * @return string
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param string $icon
     *
     * @return $this
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        return $this;
    }
}

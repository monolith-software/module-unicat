<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;
use Smart\CoreBundle\Doctrine\ColumnTrait;
use Monolith\Module\Unicat\Entity\UnicatTaxonomy;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ORM\Entity()
 * ORM\Table(name="unicat_categories"
 *      indexes={
 *          ORM\Index(name="is_enabled", columns={"is_enabled"}),
 *          ORM\Index(name="position",   columns={"position"})
 *      },
 *      uniqueConstraints={
 *          ORM\UniqueConstraint(name="slug_parent_taxonomy", columns={"slug", "parent_id", "taxonomy_id"}),
 *          ORM\UniqueConstraint(name="title_parent_taxonomy", columns={"title", "parent_id", "taxonomy_id"}),
 *      }
 * )
 *
 * @UniqueEntity(fields={"slug", "parent", "taxonomy"}, message="В каждой подкатегории должен быть уникальный сегмент URI")
 * @UniqueEntity(fields={"title", "parent", "taxonomy"}, message="В каждой подкатегории должен быть уникальный заголовок")
 */
abstract class TaxonModel
{
    use ColumnTrait\Id;
    use ColumnTrait\IsEnabled;
    use ColumnTrait\CreatedAt;
    use ColumnTrait\Position;
    use ColumnTrait\FosUser;

    /**
     * @ORM\Column(type="string", length=32)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     * @Assert\NotBlank()
     */
    protected $title;

    /**
     * Включает записи вложенных категорий.
     *
     * @ORM\Column(type="boolean", options={"default":1})
     */
    protected $is_inheritance;

    /**
     * @ORM\Column(type="array")
     */
    protected $meta;

    /**
     * @ORM\Column(type="array", nullable=true)
     *
     * @deprecated переделать на "атрибуты"
     */
    protected $properties;

    /**
     * @ORM\OneToMany(targetEntity="Taxon", mappedBy="parent")
     * @ORM\OrderBy({"position" = "ASC"})
     */
    protected $children;

    /**
     * @var TaxonModel
     *
     * @ORM\ManyToOne(targetEntity="Taxon", inversedBy="children", cascade={"persist"})
     **/
    protected $parent;

    /**
     * @var UnicatTaxonomy
     *
     * @ORM\ManyToOne(targetEntity="Monolith\Module\Unicat\Entity\UnicatTaxonomy")
     **/
    protected $taxonomy;

    /**
     * @ORM\ManyToMany(targetEntity="Item", mappedBy="taxons", fetch="EXTRA_LAZY")
     */
    protected $items;

    /**
     * @ORM\ManyToMany(targetEntity="Item", mappedBy="taxonsSingle", fetch="EXTRA_LAZY")
     */
    protected $itemsSingle;

    /**
     * Для отображения в формах. Не маппится в БД.
     */
    protected $form_title = '';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->created_at       = new \DateTime();
        $this->is_enabled       = true;
        $this->is_inheritance   = true;
        $this->meta             = [];
        $this->position         = 0;
        $this->properties       = null;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->title;
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
     * @param TaxonModel|null  $parent
     *
     * @return $this
     */
    public function setParent(TaxonModel $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return TaxonModel
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $slug
     *
     * @return $this
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param UnicatTaxonomy $taxonomy
     *
     * @return $this
     */
    public function setTaxonomy(UnicatTaxonomy $taxonomy)
    {
        $this->taxonomy = $taxonomy;

        return $this;
    }

    /**
     * @return UnicatTaxonomy
     */
    public function getTaxonomy()
    {
        return $this->taxonomy;
    }

    /**
     * @param array $meta
     *
     * @return $this
     */
    public function setMeta(array $meta)
    {
        foreach ($meta as $name => $value) {
            if (empty($value)) {
                unset($meta[$name]);
            }
        }

        $this->meta = $meta;

        return $this;
    }

    /**
     * @return array
     */
    public function getMeta()
    {
        return empty($this->meta) ? [] : $this->meta;
    }

    /**
     * @param array $properties
     *
     * @return $this
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return $this
     */
    public function setProperty($name, $value)
    {
        $this->properties[$name] = $value;

        return $this;
    }

    /**
     * @return array
     *
     * @deprecated переделать на "атрибуты"
     */
    public function getProperties()
    {
        return empty($this->properties) ? [] : $this->properties;
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     *
     * @deprecated переделать на "атрибуты"
     */
    public function getProperty($name)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     *
     * @deprecated переделать на "атрибуты"
     */
    public function getAttr($name)
    {
        return isset($this->properties[$name]) ? $this->properties[$name] : null;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasProperty($name)
    {
        return isset($this->properties[$name]) ? true : false;
    }

    /**
     * @return mixed
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @return mixed
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param string $form_title
     *
     * @return $this
     */
    public function setFormTitle($form_title)
    {
        $this->form_title = $form_title;

        return $this;
    }

    /**
     * @return string
     */
    public function getFormTitle()
    {
        return $this->form_title;
    }

    /**
     * Получить полный путь, включая родительские категории.
     *
     * @return string
     */
    public function getSlugFull()
    {
        $slug = $this->getSlug();

        if ($this->getParent()) {
            $slug  = $this->getParent()->getSlugFull().'/'.$slug;
        }

        return $slug;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = trim($title);

        return $this;
    }
}

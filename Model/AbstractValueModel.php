<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * ORM\Entity()
 * ORM\Table(name="unicat_items_attributename",
 *      indexes={
 *          ORM\Index(columns={"value"})
 *      }
 * )
 */
abstract class AbstractValueModel
{
    /**
     * @var ItemModel
     *
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Item")
     * ORM\JoinColumn(name="item_id")
     */
    protected $item;

    /**
     * @var user defined type
     *
     * ORM\Column(type="string")
     */
    //protected $value;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->item->getId();
    }

    /**
     * @param ItemModel $item
     *
     * @return $this
     */
    public function setItem(ItemModel $item)
    {
        $this->item = $item;

        return $this;
    }

    /**
     * @return ItemModel
     */
    public function getItem()
    {
        return $this->item;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}

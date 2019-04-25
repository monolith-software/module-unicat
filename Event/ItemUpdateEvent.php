<?php

namespace Monolith\Module\Unicat\Event;

use Monolith\Module\Unicat\Model\ItemModel;
use Symfony\Component\EventDispatcher\Event;

class ItemUpdateEvent extends Event
{
    /** @var  ItemModel */
    protected $item;

    /**
     * ItemUpdateEvent constructor.
     *
     * @param ItemModel $item
     */
    public function __construct(ItemModel $item)
    {
        $this->item = $item;
    }

    /**
     * @return ItemModel
     */
    public function getItem(): ItemModel
    {
        return $this->item;
    }
}

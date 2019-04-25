<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * {@inheritDoc}
 */
abstract class ValueDateModel extends AbstractValueModel
{
    /**
     * @ORM\Column(type="date")
     */
    protected $value;
}

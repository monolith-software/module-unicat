<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * {@inheritDoc}
 */
abstract class ValueFloatModel extends AbstractValueModel
{
    /**
     * @ORM\Column(type="float")
     */
    protected $value;
}

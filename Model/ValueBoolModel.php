<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * {@inheritDoc}
 */
abstract class ValueBoolModel extends AbstractValueModel
{
    /**
     * @ORM\Column(type="boolean")
     */
    protected $value;
}

<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * {@inheritDoc}
 */
abstract class ValueStringModel extends AbstractValueModel
{
    /**
     * @ORM\Column(type="string")
     */
    protected $value;
}

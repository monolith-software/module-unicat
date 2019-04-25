<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * {@inheritDoc}
 */
abstract class ValueSmallintModel extends AbstractValueModel
{
    /**
     * @ORM\Column(type="smallint")
     */
    protected $value;
}

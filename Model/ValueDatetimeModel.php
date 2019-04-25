<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * {@inheritDoc}
 */
abstract class ValueDatetimeModel extends AbstractValueModel
{
    /**
     * @ORM\Column(type="datetime")
     */
    protected $value;
}

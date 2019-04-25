<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * {@inheritDoc}
 */
abstract class ValueTextModel extends AbstractValueModel
{
    /**
     * @ORM\Column(type="text")
     */
    protected $value;
}

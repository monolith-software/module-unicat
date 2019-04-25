<?php

namespace Monolith\Module\Unicat\Controller;

use Monolith\Module\Unicat\Service\UnicatConfigurationManager;

trait UnicatTrait
{
    /** @var  UnicatConfigurationManager */
    protected $unicat;

    /**
     * @return mixed
     */
    public function getUnicat()
    {
        return $this->unicat;
    }

    /**
     * @param mixed $unicat
     *
     * @return $this
     */
    public function setUnicat($unicat)
    {
        $this->unicat = $unicat;

        return $this;
    }
}

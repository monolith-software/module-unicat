<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\EntityRepository;
use Smart\CoreBundle\Doctrine\RepositoryTrait;

class TaxonRepository extends EntityRepository
{
    //use RepositoryTrait\Count;
    use RepositoryTrait\FindByQuery;
}

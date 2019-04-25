<?php

namespace Monolith\Module\Unicat\Model;

use Doctrine\ORM\EntityRepository;
use Smart\CoreBundle\Doctrine\RepositoryTrait;

class ItemRepository extends EntityRepository
{
//    use RepositoryTrait\Count;
    use RepositoryTrait\FindByQuery;
}

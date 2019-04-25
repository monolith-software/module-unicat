<?php

namespace Monolith\Module\Unicat\Entity;

use Doctrine\ORM\EntityRepository;

class UnicatAttributeRepository extends EntityRepository
{
    /**
     * @param int|UnicatConfiguration $configuration
     * @param array $groups
     *
     * @return UnicatAttribute[]
     */
    public function findByGroupsNames($configuration, array $groups)
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.groups', 'g')
            ->orderBy('e.position', 'ASC')
        ;

        $first = true;
        foreach ($groups as $key => $group) {
            if ($first) {
                $qb->where('g.name = :name'.$key);
                $first = false;
            } else {
                $qb->orWhere('g.name = :name'.$key);
            }

            $qb->setParameter('name'.$key, $group);
        }

        $qb->andWhere('g.configuration = :configuration');
        $qb->setParameter('configuration', $configuration);

        return $qb->getQuery()->getResult();
    }
}

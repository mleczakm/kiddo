<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Child;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Child>
 */
class ChildRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Child::class);
    }

    /**
     * @return Child[]
     */
    public function findByOwner(User $user): array
    {
        /** @var list<Child> $children */
        $children = $this->createQueryBuilder('c')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $children;
    }
}

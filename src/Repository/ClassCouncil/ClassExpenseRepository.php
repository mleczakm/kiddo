<?php

declare(strict_types=1);

namespace App\Repository\ClassCouncil;

use App\Entity\ClassCouncil\ClassExpense;
use App\Entity\ClassCouncil\ClassRoom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassExpense>
 */
class ClassExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassExpense::class);
    }

    /**
     * @return array<int, ClassExpense>
     */
    public function findByClass(ClassRoom $classRoom): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.classRoom = :class')
            ->setParameter('class', $classRoom->getId(), 'ulid')
            ->orderBy('e.spentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

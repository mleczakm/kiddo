<?php

declare(strict_types=1);

namespace App\Repository\ClassCouncil;

use App\Entity\ClassCouncil\ClassMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassMembership>
 */
class ClassMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassMembership::class);
    }
}

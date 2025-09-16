<?php

declare(strict_types=1);

namespace App\Repository\ClassCouncil;

use App\Entity\ClassCouncil\ClassRoom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassRoom>
 */
class ClassRoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassRoom::class);
    }
}

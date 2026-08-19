<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LessonMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LessonMetadata>
 */
class LessonMetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonMetadata::class);
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function slugExists(string $slug): bool
    {
        $count = $this
            ->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}

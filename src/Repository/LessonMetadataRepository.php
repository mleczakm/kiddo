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

    /** @return list<array{slug: string, title: string}> */
    public function findDistinctSlugsForOptions(int $limit = 300): array
    {
        $rows = $this
            ->createQueryBuilder('m')
            ->select('DISTINCT m.slug AS slug', 'm.title AS title')
            ->orderBy('m.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn(array $row): array => [
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
        ], $rows));
    }
}

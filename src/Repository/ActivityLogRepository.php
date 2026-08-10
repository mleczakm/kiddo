<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return ActivityLog[]
     */
    public function findRecent(int $limit = 12): array
    {
        /** @var list<ActivityLog> $result */
        $result = $this->createQueryBuilder('a')
            // createdAt has only second precision in the DB; break ties with the
            // ULID (itself time-ordered) so rows created in the same second still
            // come back in the order they actually happened.
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return ActivityLog[]
     */
    public function findBySubject(User $subject, int $limit = 20): array
    {
        /** @var list<ActivityLog> $result */
        $result = $this->createQueryBuilder('a')
            ->andWhere('a.subject = :subject')
            ->setParameter('subject', $subject)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function existsByDedupeKey(string $dedupeKey): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.dedupeKey = :key')
            ->setParameter('key', $dedupeKey)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}

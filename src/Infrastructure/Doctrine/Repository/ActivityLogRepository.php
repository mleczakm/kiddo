<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\ActivityLogRepositoryInterface;
use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository implements ActivityLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return ActivityLog[]
     */
    #[\Override]
    public function findRecent(int $limit = 12): array
    {
        /** @var list<ActivityLog> $result */
        return $this
            ->createQueryBuilder('a')
            // createdAt has only second precision in the DB; break ties with the
            // ULID (itself time-ordered) so rows created in the same second still
            // come back in the order they actually happened.
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    #[\Override]
    public function findBySubject(User $subject, int $limit = 20): array
    {
        /** @var list<ActivityLog> $result */
        return $this
            ->createQueryBuilder('a')
            ->andWhere('a.subject = :subject')
            ->setParameter('subject', $subject)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    #[\Override]
    public function findByBookingId(string $bookingId, int $limit = 20): array
    {
        /** @var list<ActivityLog> $result */
        return $this
            ->createQueryBuilder('a')
            ->andWhere("JSON_GET_TEXT(a.context, 'bookingId') = :bookingId")
            ->setParameter('bookingId', $bookingId)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    #[\Override]
    public function findByPricingRuleId(string $pricingRuleId, int $limit = 20): array
    {
        /** @var list<ActivityLog> $result */
        return $this
            ->createQueryBuilder('a')
            ->andWhere("JSON_GET_TEXT(a.context, 'pricingRuleId') = :pricingRuleId")
            ->setParameter('pricingRuleId', $pricingRuleId)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function existsByDedupeKey(string $dedupeKey): bool
    {
        $count = $this
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.dedupeKey = :key')
            ->setParameter('key', $dedupeKey)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}

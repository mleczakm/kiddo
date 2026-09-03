<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\SubscriptionRepositoryInterface;
use App\Entity\Series;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository implements SubscriptionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * @return list<Subscription>
     */
    #[\Override]
    public function findActiveForUser(User $user): array
    {
        /** @var list<Subscription> */
        return $this
            ->createQueryBuilder('s')
            ->select('s', 'series', 'c')
            ->join('s.series', 'series')
            ->leftJoin('s.child', 'c')
            ->where('s.user = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Subscription>
     */
    #[\Override]
    public function findAllActive(): array
    {
        /** @var list<Subscription> */
        return $this
            ->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function findActiveFor(User $user, Series $series, ?string $childId): ?Subscription
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.series = :series')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('series', $series->getId(), 'ulid')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->setMaxResults(1);

        if ($childId === null || $childId === '') {
            $qb->andWhere('s.child IS NULL');

            /** @var list<Subscription> $anonymous */
            $anonymous = $qb->getQuery()->getResult();

            return $anonymous[0] ?? null;
        }

        $qb->andWhere('s.child = :child')->setParameter('child', $childId, 'ulid');

        /** @var list<Subscription> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows[0] ?? null;
    }
}

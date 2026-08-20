<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\NotificationRepositoryInterface;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository implements NotificationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    #[\Override]
    public function findRecentForUser(User $user, int $limit = 20): array
    {
        /** @var list<Notification> $notifications */
        return $this
            ->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->andWhere('n.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function countUnreadForUser(User $user): int
    {
        return (int) $this
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.deletedAt IS NULL')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    #[\Override]
    public function hardDeleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        $deleted = $this
            ->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        return is_int($deleted) ? $deleted : 0;
    }
}

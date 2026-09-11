<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\WaitlistEntryRepositoryInterface;
use App\Entity\Lesson;
use App\Entity\User;
use App\Entity\WaitlistEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WaitlistEntry>
 */
class WaitlistEntryRepository extends ServiceEntityRepository implements WaitlistEntryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaitlistEntry::class);
    }

    #[\Override]
    public function findActiveForUserAndLesson(User $user, Lesson $lesson): ?WaitlistEntry
    {
        /** @var list<WaitlistEntry> $rows */
        $rows = $this
            ->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.lesson = :lesson')
            ->andWhere('w.status IN (:active)')
            ->setParameter('user', $user)
            ->setParameter('lesson', $lesson->getId(), 'ulid')
            ->setParameter('active', WaitlistEntry::ACTIVE_STATUSES)
            ->orderBy('w.queuedAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $rows[0] ?? null;
    }

    /**
     * @return list<WaitlistEntry>
     */
    #[\Override]
    public function findActiveForLesson(Lesson $lesson): array
    {
        /** @var list<WaitlistEntry> $rows */
        $rows = $this
            ->createQueryBuilder('w')
            ->andWhere('w.lesson = :lesson')
            ->andWhere('w.status IN (:active)')
            ->setParameter('lesson', $lesson->getId(), 'ulid')
            ->setParameter('active', WaitlistEntry::ACTIVE_STATUSES)
            ->orderBy('w.queuedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values($rows);
    }

    #[\Override]
    public function countActiveOffers(Lesson $lesson): int
    {
        try {
            return (int) $this
                ->createQueryBuilder('w')
                ->select('COUNT(w.id)')
                ->andWhere('w.lesson = :lesson')
                ->andWhere('w.status = :offered')
                ->setParameter('lesson', $lesson->getId(), 'ulid')
                ->setParameter('offered', WaitlistEntry::STATUS_OFFERED)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException) {
            return 0;
        }
    }

    #[\Override]
    public function countActiveForLesson(Lesson $lesson): int
    {
        try {
            return (int) $this
                ->createQueryBuilder('w')
                ->select('COUNT(w.id)')
                ->andWhere('w.lesson = :lesson')
                ->andWhere('w.status IN (:active)')
                ->setParameter('lesson', $lesson->getId(), 'ulid')
                ->setParameter('active', WaitlistEntry::ACTIVE_STATUSES)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException|NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * @return list<WaitlistEntry>
     */
    #[\Override]
    public function findExpiredOffers(\DateTimeImmutable $now): array
    {
        /** @var list<WaitlistEntry> $rows */
        $rows = $this
            ->createQueryBuilder('w')
            ->addSelect('l', 'u')
            ->join('w.lesson', 'l')
            ->join('w.user', 'u')
            ->andWhere('w.status = :offered')
            ->andWhere('w.offerExpiresAt <= :now')
            ->setParameter('offered', WaitlistEntry::STATUS_OFFERED)
            ->setParameter('now', $now)
            ->orderBy('w.offerExpiresAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values($rows);
    }

    /**
     * @return list<WaitlistEntry>
     */
    #[\Override]
    public function findActiveForUser(User $user): array
    {
        /** @var list<WaitlistEntry> $rows */
        $rows = $this
            ->createQueryBuilder('w')
            ->addSelect('l')
            ->join('w.lesson', 'l')
            ->andWhere('w.user = :user')
            ->andWhere('w.status IN (:active)')
            ->setParameter('user', $user)
            ->setParameter('active', WaitlistEntry::ACTIVE_STATUSES)
            ->orderBy('w.queuedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values($rows);
    }
}

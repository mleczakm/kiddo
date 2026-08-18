<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return array<Booking>
     */
    public function findExpiredPendingBookings(\DateTimeImmutable $expirationTime): array
    {
        /** @var array<Booking> $bookings */
        $bookings = $this
            ->createQueryBuilder('b')
            ->leftJoin('b.payment', 'p')
            ->where('b.status = :status')
            ->andWhere('b.createdAt < :expirationTime')
            ->andWhere('p.status IS NULL OR p.status != :paidStatus')
            ->setParameter('status', Booking::STATUS_PENDING)
            ->setParameter('expirationTime', $expirationTime)
            ->setParameter('paidStatus', Payment::STATUS_PAID)
            ->getQuery()
            ->getResult();

        return $bookings;
    }

    /**
     * @return array<Booking>
     */
    public function findActiveBookings(): array
    {
        /** @var array<Booking> $bookings */
        $bookings = $this
            ->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', Booking::STATUS_ACTIVE)
            ->getQuery()
            ->getResult();

        return $bookings;
    }

    /**
     * Every booking a user could plausibly want to see on their dashboard —
     * pending, active, past, or cancelled (including auto-cancelled) —
     * fully hydrated with lessons/series/payment.
     *
     * Deliberately does NOT filter by lesson date at the SQL level: doing so
     * would make Doctrine partially hydrate `booking.lessons` from just the
     * joined+filtered rows, which is unreliable for a collection that's
     * meant to represent the *complete* set of lessons on the booking. Tab
     * filtering (active/past/cancelled) is left to the caller, since it's a
     * per-*lesson* decision, not a per-*booking* one — a single carnet
     * booking can have lessons in more than one of those states at once.
     *
     * @return list<Booking>
     */
    public function findVisibleForUser(User $user): array
    {
        /** @var list<Booking> $result */
        $result = $this
            ->createQueryBuilder('b')
            ->select('b', 'l', 's', 'p')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('l.series', 's')
            ->leftJoin('b.payment', 'p')
            ->where('b.user = :user')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                Booking::STATUS_PENDING,
                Booking::STATUS_ACTIVE,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_COMPLETED,
            ])
            ->orderBy('l.schedule', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Find non-cancelled bookings for a user and specific lessons
     *
     * @param array<Lesson> $lessons
     *
     * @return array<Booking>
     */
    public function findForUserAndLessons(User $user, array $lessons): array
    {
        if ($lessons === []) {
            return [];
        }

        $qb = $this
            ->createQueryBuilder('b')
            ->innerJoin('b.lessons', 'l')
            ->leftJoin('b.payment', 'p')
            ->leftJoin('b.child', 'c')
            ->addSelect('l', 'p', 'c')
            ->where('b.user = :user')
            ->andWhere('b.status != :cancelled')
            ->setParameter('user', $user)
            ->setParameter('cancelled', Booking::STATUS_CANCELLED);

        // Bind each Ulid with the doctrine "ulid" type so Postgres receives UUID (RFC4122),
        // not the Ulid base32 string (IN (:ids) with a string array skips Ulid conversion).
        $orX = $qb->expr()->orX();
        foreach (array_values($lessons) as $index => $lesson) {
            $param = 'lessonId' . $index;
            $orX->add($qb->expr()->eq('l.id', ':' . $param));
            $qb->setParameter($param, $lesson->getId(), 'ulid');
        }
        $qb->andWhere($orX);

        /** @var array<Booking> $bookings */
        $bookings = $qb->getQuery()->getResult();

        return $bookings;
    }

    /**
     * Find non-cancelled bookings for a user and a specific lesson
     *
     * @return array<Booking>
     */
    public function findForUserAndLesson(User $user, Lesson $lesson): array
    {
        return $this->findForUserAndLessons($user, [$lesson]);
    }

    /**
     * @return array<Booking>
     */
    public function findByLesson(Lesson $lesson): array
    {
        /** @var array<Booking> $bookings */
        $bookings = $this
            ->createQueryBuilder('b')
            ->innerJoin('b.lessons', 'l')
            ->where('l.id = :lessonId')
            ->setParameter('lessonId', $lesson->getId(), 'ulid')
            ->getQuery()
            ->getResult();

        return $bookings;
    }

    public function countCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this
            ->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.createdAt BETWEEN :start AND :end')
            ->andWhere('b.status != :cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('cancelled', Booking::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<Booking>
     */
    public function findCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var array<Booking> $bookings */
        $bookings = $this
            ->createQueryBuilder('b')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('b.user', 'u')
            ->leftJoin('b.child', 'c')
            ->addSelect('l', 'u', 'c')
            ->where('b.createdAt BETWEEN :start AND :end')
            ->andWhere('b.status != :cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('cancelled', Booking::STATUS_CANCELLED)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $bookings;
    }
}

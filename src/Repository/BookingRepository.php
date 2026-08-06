<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Lesson;
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
        $bookings =  $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->andWhere('b.createdAt < :expirationTime')
            ->setParameter('status', Booking::STATUS_PENDING)
            ->setParameter('expirationTime', $expirationTime)
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
        $bookings = $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', Booking::STATUS_ACTIVE)
            ->getQuery()
            ->getResult();

        return $bookings;
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

        $qb = $this->createQueryBuilder('b')
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
        $orX = $qb->expr()
            ->orX();
        foreach (array_values($lessons) as $index => $lesson) {
            $param = 'lessonId' . $index;
            $orX->add($qb->expr()->eq('l.id', ':' . $param));
            $qb->setParameter($param, $lesson->getId(), 'ulid');
        }
        $qb->andWhere($orX);

        /** @var array<Booking> $bookings */
        $bookings = $qb->getQuery()
            ->getResult();

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
}

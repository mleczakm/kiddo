<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\LessonRepositoryInterface;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Series;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ds\PriorityQueue;
use Ds\Vector;

/**
 * @extends ServiceEntityRepository<Lesson>
 */
class LessonRepository extends ServiceEntityRepository implements LessonRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lesson::class);
    }

    /**
     * Finds available lessons for rescheduling a booking.
     *
     * @param Series $series The series to find lessons in
     * @param \DateTimeInterface $afterDate Only return lessons after this date
     * @param int $maxResults Maximum number of results to return
     * @return array<int, Lesson>
     */
    /**
     * @return array<int, Lesson>
     */
    #[\Override]
    public function findAvailableLessonsForReschedule(
        Series $series,
        \DateTimeInterface $afterDate,
        int $maxResults = 10,
    ): array {
        $qb = $this->createQueryBuilder('l');

        /** @var array<int, Lesson> $result */
        return $qb
            ->leftJoin('l.series', 'visibilitySeries')
            ->andWhere('l.schedule > :afterDate')
            ->andWhere('l.status = :status')
            ->andWhere('l.visible = true')
            ->andWhere('visibilitySeries.id IS NULL OR visibilitySeries.visible = true')
            ->andWhere('l.series = :series')
            ->setParameter('afterDate', $afterDate)
            ->setParameter('status', 'active')
            ->setParameter('series', $series->getId(), 'ulid')
            ->orderBy('l.schedule', 'ASC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Lesson[]
     */
    #[\Override]
    public function findActiveByDate(DateTimeImmutable $date): array
    {
        $start = $date->setTime(0, 0, 0);
        $end = $date->setTime(23, 59, 59);

        /** @var Lesson[] $result */
        return $this
            ->createQueryBuilder('l')
            ->leftJoin('l.series', 'visibilitySeries')
            ->leftJoin('l.bookings', 'b')
            ->leftJoin('b.child', 'c')
            ->leftJoin('b.user', 'u')
            ->addSelect('b, c, u')
            ->where('l.schedule >= :start')
            ->andWhere('l.schedule <= :end')
            ->andWhere('l.status = :status')
            ->andWhere('l.visible = true')
            ->andWhere('visibilitySeries.id IS NULL OR visibilitySeries.visible = true')
            ->setParameter('status', 'active')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Lesson[]
     */
    #[\Override]
    public function findByFilters(
        ?string $query,
        ?int $age,
        string $week,
        ?int $limit = null,
        bool $orderByPopularity = false,
    ): array {
        $qb = $this
            ->createQueryBuilder('l')
            ->join('l.metadata', 'm')
            ->leftJoin('l.series', 'visibilitySeries')
            ->andWhere('l.status = :status')
            ->andWhere('l.visible = true')
            ->andWhere('visibilitySeries.id IS NULL OR visibilitySeries.visible = true')
            ->setParameter('status', 'active')
            ->orderBy('l.schedule', 'ASC');

        if ($query !== null) {
            $qb->andWhere('ILIKE(m.title, :query) = TRUE')->setParameter('query', '%' . $query . '%');
        }

        if ($age !== null) {
            $qb->andWhere('m.ageRange.min <= :age')->andWhere('m.ageRange.max >= :age')->setParameter('age', $age);
        }

        $weekStart = new \DateTimeImmutable($week);
        $weekEnd = $weekStart->modify('+7 days 23:59:59');

        $qb
            ->andWhere('l.schedule BETWEEN :weekStart AND :weekEnd')
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd);

        if ($limit !== null && !$orderByPopularity) {
            $qb->setMaxResults($limit);
        }

        /** @var Lesson[] $result */
        $result = $qb->getQuery()->getResult();

        if ($orderByPopularity) {
            /** @var Lesson[] $result */
            $result = new Vector($result)
                ->reduce(static function (PriorityQueue $queue, Lesson $lesson): PriorityQueue {
                    $queue->push($lesson, $lesson->getBookings()->count());

                    return $queue;
                }, new PriorityQueue())
                ?->toArray();

            if ($limit !== null) {
                $result = array_slice($result, 0, $limit);
            }
        }

        return $result;
    }

    /**
     * @return array<int, Lesson>
     */
    #[\Override]
    public function findUpcoming(\DateTimeImmutable $since, int $limit): array
    {
        /** @var Lesson[] $lessons */
        return $this
            ->createQueryBuilder('l')
            ->leftJoin('l.bookings', 'b')
            ->where('l.schedule > :since')
            ->setParameter('since', $since)
            ->orderBy('l.schedule', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, Lesson>
     */
    #[\Override]
    public function findUpcomingWithBookings(\DateTimeImmutable $since, int $limit): array
    {
        /** @var Lesson[] $lessons */
        return $this
            ->createQueryBuilder('l')
            ->leftJoin('l.bookings', 'b')
            ->leftJoin('b.child', 'c')
            ->leftJoin('b.user', 'u')
            ->addSelect('b, c, u')
            ->andWhere('l.schedule > :since')
            ->andWhere('l.status = :status')
            ->setParameter('status', 'active')
            ->setParameter('since', $since)
            ->orderBy('l.schedule', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, Lesson>
     */
    #[\Override]
    public function findUpcomingWithBookingsInRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $showCancelled = false,
    ): array {
        $qb = $this
            ->createQueryBuilder('l')
            ->leftJoin('l.bookings', 'b')
            ->leftJoin('b.child', 'c')
            ->leftJoin('b.user', 'u')
            ->addSelect('b, c, u')
            ->andWhere('l.schedule >= :startDate')
            ->andWhere('l.schedule <= :endDate')
            // include lessons even if they have no bookings
            ->andWhere('(b.status IN (:bookingStatus) OR b.id IS NULL)')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('l.schedule', 'ASC');

        if (!$showCancelled) {
            $qb
                ->andWhere('l.status = :status')
                ->setParameter('status', 'active')
                ->setParameter('bookingStatus', [Booking::STATUS_PENDING, Booking::STATUS_ACTIVE]);
        } else {
            $qb->setParameter('bookingStatus', [
                Booking::STATUS_PENDING,
                Booking::STATUS_ACTIVE,
                Booking::STATUS_CANCELLED,
            ]);
        }

        /** @var Lesson[] $lessons */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Lesson>
     */
    #[\Override]
    public function findUpcomingInRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $showCancelled = false,
    ): array {
        $qb = $this
            ->createQueryBuilder('l')
            ->andWhere('l.schedule >= :startDate')
            ->andWhere('l.schedule <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('l.schedule', 'ASC');

        if (!$showCancelled) {
            $qb->andWhere('l.status = :status')->setParameter('status', 'active');
        }

        /** @var Lesson[] $lessons */
        return $qb->getQuery()->getResult();
    }

    /**
     * Same as findUpcomingInRange(), scoped to lessons where $instructor is
     * an assigned instructor — either directly on the lesson or on its
     * series (mirrors Lesson::getAllInstructors() semantics). Used for the
     * ROLE_HOST admin views, which only ever see their own lessons.
     *
     * @return array<int, Lesson>
     */
    #[\Override]
    public function findUpcomingInRangeForInstructor(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $showCancelled,
        User $instructor,
    ): array {
        $qb = $this
            ->createQueryBuilder('l')
            ->leftJoin('l.series', 's')
            ->andWhere('l.schedule >= :startDate')
            ->andWhere('l.schedule <= :endDate')
            ->andWhere(':instructor MEMBER OF l.instructors OR :instructor MEMBER OF s.instructors')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('instructor', $instructor)
            ->orderBy('l.schedule', 'ASC');

        if (!$showCancelled) {
            $qb->andWhere('l.status = :status')->setParameter('status', 'active');
        }

        /** @var Lesson[] $lessons */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Lesson>
     */
    #[\Override]
    public function findCancelledInRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var list<Lesson> $result */
        return $this
            ->createQueryBuilder('l')
            ->andWhere('l.status = :status')
            ->andWhere('l.schedule BETWEEN :start AND :end')
            ->setParameter('status', 'cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('l.schedule', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Lesson>
     */
    #[\Override]
    public function findByMetadataTitlePrefix(string $prefix): array
    {
        /** @var list<Lesson> $result */
        return $this
            ->createQueryBuilder('l')
            ->join('l.metadata', 'm')
            ->where('m.title LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lesson;
use App\Entity\Series;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lesson>
 */
class LessonRepository extends ServiceEntityRepository
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
    public function findAvailableLessonsForReschedule(
        Series $series,
        \DateTimeInterface $afterDate,
        int $maxResults = 10
    ): array {
        $qb = $this->createQueryBuilder('l');

        /** @var array<int, Lesson> $result */
        $result = $qb
            ->andWhere('l.metadata.schedule > :afterDate')
            ->andWhere('l.status = :status')
            ->andWhere('l.series = :series')
            ->setParameter('afterDate', $afterDate)
            ->setParameter('status', 'active')
            ->setParameter('series', $series->getId(), 'ulid')
            ->orderBy('l.metadata.schedule', 'ASC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return Lesson[]
     */
    public function findActiveByDate(DateTimeImmutable $date): array
    {
        $start = $date->setTime(0, 0, 0);
        $end = $date->setTime(23, 59, 59);

        /** @var Lesson[] $result */
        $result = $this->createQueryBuilder('l')
            ->where('l.metadata.schedule >= :start')
            ->andWhere('l.metadata.schedule <= :end')
            ->andWhere('l.status = :status')
            ->setParameter('status', 'active')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return Lesson[]
     */
    public function findByFilters(?string $query, ?int $age, string $week): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('l.metadata.schedule', 'ASC');

        if ($query !== null) {
            $qb->andWhere('ILIKE(l.metadata.title, :query) = TRUE')
                ->setParameter('query', '%' . $query . '%');
        }

        if ($age !== null) {
            $qb->andWhere('l.metadata.ageRange.min <= :age')
                ->andWhere('l.metadata.ageRange.max >= :age')
                ->setParameter('age', $age);
        }

        $weekStart = new \DateTimeImmutable($week)
            ->modify('monday this week');
        $weekEnd = $weekStart->modify('sunday this week 23:59:59');

        $qb->andWhere('l.metadata.schedule BETWEEN :weekStart AND :weekEnd')
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd);

        /** @var Lesson[] $result */
        $result = $qb->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<int, Lesson>
     */
    public function findUpcoming(\DateTimeImmutable $since, int $limit): array
    {
        /** @var Lesson[] $lessons */
        $lessons = $this->createQueryBuilder('l')
            ->leftJoin('l.bookings', 'b')
            ->where('l.metadata.schedule > :since')
            ->setParameter('since', $since)
            ->orderBy('l.metadata.schedule', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $lessons;
    }

    /**
     * @return array<int, Lesson>
     */
    public function findUpcomingWithBookings(\DateTimeImmutable $since, int $limit): array
    {
        /** @var Lesson[] $lessons */
        $lessons = $this->createQueryBuilder('l')
            ->leftJoin('l.bookings', 'b')
            ->andWhere('l.metadata.schedule > :since')
            ->andWhere('l.status = :status')
            ->setParameter('status', 'active')
            ->setParameter('since', $since)
            ->orderBy('l.metadata.schedule', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $lessons;
    }

    /**
     * @return array<int, Lesson>
     */
    public function findUpcomingWithBookingsInRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $showCancelled = false
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.bookings', 'b')
            ->andWhere('l.metadata.schedule >= :startDate')
            ->andWhere('l.metadata.schedule <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('l.metadata.schedule', 'ASC');

        if (! $showCancelled) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', 'active');
        }

        /** @var Lesson[] $lessons */
        $lessons = $qb->getQuery()
            ->getResult();

        return $lessons;
    }

    /**
     * @return array<int, Lesson>
     */
    public function findUpcomingInRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        bool $showCancelled = false
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.metadata.schedule >= :startDate')
            ->andWhere('l.metadata.schedule <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('l.metadata.schedule', 'ASC');

        if (! $showCancelled) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', 'active');
        }

        /** @var Lesson[] $lessons */
        $lessons = $qb->getQuery()
            ->getResult();

        return $lessons;
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

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
     * @return Lesson[]
     */
    public function findAvailableLessonsForReschedule(
        Series $series,
        \DateTimeInterface $afterDate,
        int $maxResults = 10
    ): array {
        $qb = $this->createQueryBuilder('l');

        return $qb
            ->andWhere('l.metadata.schedule > :afterDate')
            ->andWhere('l.status = :status')
            ->setParameter('afterDate', $afterDate)
            ->setParameter('status', 'active')
            ->orderBy('l.metadata.schedule', 'ASC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array<int, Lesson>
     */
    public function findAvailableLessonsForUserReschedule(
        Series $series,
        \DateTimeInterface $afterDate,
        User $user,
        int $maxResults = 10
    ): array {
        $qb = $this->createQueryBuilder('l');

        /** @var array<int, Lesson> $result */
        $result = $qb
            ->leftJoin('l.bookings', 'b')
            ->leftJoin('b.user', 'u')
            ->andWhere('l.metadata.schedule > :afterDate')
            ->andWhere('l.status = :status')
            ->andWhere('l.series = :series')
            ->setParameter('afterDate', $afterDate)
            ->setParameter('status', 'active')
            ->setParameter('series', $series->getId(), 'ulid')
            ->setParameter('user', $user)
            ->setParameter('activeStatuses', ['confirmed', 'pending'])
            ->orderBy('l.metadata.schedule', 'ASC')
            ->setMaxResults($maxResults)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return Lesson[]
     */
    public function findByDate(DateTimeImmutable $date): array
    {
        $start = $date->setTime(0, 0, 0);
        $end = $date->setTime(23, 59, 59);

        /** @var Lesson[] $result */
        $result = $this->createQueryBuilder('l')
            ->where('l.metadata.schedule >= :start')
            ->andWhere('l.metadata.schedule <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return Lesson[]
     */
    public function findByFilters(
        ?string $query,
        ?int $age,
        string $week,
        ?int $limit = null,
        bool $orderByPopularity = false
    ): array {
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

        $weekStart = new \DateTimeImmutable($week);
        $weekEnd = $weekStart->modify('+7 days 23:59:59');

        $qb->andWhere('l.metadata.schedule BETWEEN :weekStart AND :weekEnd')
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd);

        if ($limit !== null && ! $orderByPopularity) {
            $qb->setMaxResults($limit);
        }

        /** @var Lesson[] $result */
        $result = $qb->getQuery()
            ->getResult();

        if ($orderByPopularity) {
            /** @var Lesson[] $result */
            $result = new Vector($result)
                ->reduce(
                    fn(PriorityQueue $queue, Lesson $lesson) => $queue->push($lesson, $lesson->getBookings()->count()),
                    new PriorityQueue()
                )?->toArray();

            if ($limit !== null) {
                $result = array_slice($result, 0, $limit);
            }
        }

        return $result;
    }
}

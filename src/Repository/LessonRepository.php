<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lesson;
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
}

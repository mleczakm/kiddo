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
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Series;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Series>
 */
class SeriesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Series::class);
    }

    /**
     * @return array<int, Series>
     */
    public function findInRange(\DateTimeImmutable $start, \DateTimeImmutable $end, bool $showCancelled = false): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.lessons', 'l')
            ->andWhere('l.metadata.schedule BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('l.metadata.schedule', 'ASC')
        ;

        if (! $showCancelled) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', 'active');
        }

        /** @var array<int, Series> $result */
        $result = $qb->getQuery()
            ->getResult();
        return $result;
    }

    /**
     * @return array<int, Series>
     */
    public function findActive(): array
    {
        /** @var array<int, Series> $result */
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getResult();

        return $result;
    }
}

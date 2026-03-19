<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Transfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transfer>
 */
class TransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transfer::class);
    }

    /**
     * Find transfers including soft-deleted ones
     * @return Transfer[]
     */
    public function findAllWithDeleted(): array
    {
        $this->getEntityManager()
            ->getFilters()
            ->disable('softdeleteable');
        try {
            return $this->findAll();
        } finally {
            $this->getEntityManager()
                ->getFilters()
                ->enable('softdeleteable');
        }
    }

    /**
     * Find only soft-deleted transfers
     * @return Transfer[]
     */
    public function findOnlyDeleted(): array
    {
        $this->getEntityManager()
            ->getFilters()
            ->disable('softdeleteable');
        try {
            /** @var Transfer[] $result */
            $result = $this->createQueryBuilder('t')
                ->where('t.deletedAt IS NOT NULL')
                ->getQuery()
                ->getResult();

            return $result;
        } finally {
            $this->getEntityManager()
                ->getFilters()
                ->enable('softdeleteable');
        }
    }

    /**
     * Restore a soft-deleted transfer
     */
    public function restore(Transfer $transfer): void
    {
        $this->getEntityManager()
            ->getFilters()
            ->disable('softdeleteable');
        try {
            $transfer->setDeletedAt(null);
            $this->getEntityManager()
                ->persist($transfer);
            $this->getEntityManager()
                ->flush();
        } finally {
            $this->getEntityManager()
                ->getFilters()
                ->enable('softdeleteable');
        }
    }
}

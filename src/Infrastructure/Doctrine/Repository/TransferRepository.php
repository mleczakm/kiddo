<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\TransferRepositoryInterface;
use App\Entity\Transfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transfer>
 */
class TransferRepository extends ServiceEntityRepository implements TransferRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transfer::class);
    }

    /**
     * Find transfers including soft-deleted ones
     * @return Transfer[]
     */
    #[\Override]
    public function findAllWithDeleted(): array
    {
        $this->getEntityManager()->getFilters()->disable('softdeleteable');
        try {
            return $this->findAll();
        } finally {
            $this->getEntityManager()->getFilters()->enable('softdeleteable');
        }
    }

    /**
     * Find only soft-deleted transfers
     * @return Transfer[]
     */
    #[\Override]
    public function findOnlyDeleted(): array
    {
        $this->getEntityManager()->getFilters()->disable('softdeleteable');
        try {
            /** @var Transfer[] $result */
            return $this->createQueryBuilder('t')->where('t.deletedAt IS NOT NULL')->getQuery()->getResult();
        } finally {
            $this->getEntityManager()->getFilters()->enable('softdeleteable');
        }
    }

    /**
     * @return Transfer[]
     */
    #[\Override]
    public function findByTitleStartingWith(string $prefix): array
    {
        /** @var Transfer[] $result */
        return $this
            ->createQueryBuilder('t')
            ->where('t.title LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Restore a soft-deleted transfer
     */
    #[\Override]
    public function restore(Transfer $transfer): void
    {
        $this->getEntityManager()->getFilters()->disable('softdeleteable');
        try {
            $transfer->setDeletedAt(null);
            $this->getEntityManager()->persist($transfer);
            $this->getEntityManager()->flush();
        } finally {
            $this->getEntityManager()->getFilters()->enable('softdeleteable');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FinanceContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinanceContact>
 */
class FinanceContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinanceContact::class);
    }

    /**
     * @return FinanceContact[]
     */
    #[\Override]
    public function findAll(): array
    {
        /** @var FinanceContact[] $contacts */
        $contacts = $this->createQueryBuilder('fc')
            ->orderBy('fc.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $contacts;
    }
}

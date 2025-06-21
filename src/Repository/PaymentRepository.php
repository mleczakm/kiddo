<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @return array<Payment>
     */
    public function findExpiredPendingPayments(int $expirationMinutes): array
    {
        $expirationTime = new \DateTimeImmutable(sprintf('-%d minutes', $expirationMinutes));

        /** @var array<Payment> $result */
        $result = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.createdAt < :expirationTime')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->setParameter('expirationTime', $expirationTime)
            ->getQuery()
            ->getResult();

        return $result;
    }
}

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

    /**
     * @return Payment[]
     */
    public function findPendingWithSearch(string $search): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING);

        if ($search) {
            $qb->join('p.user', 'u')
                ->join('p.paymentCode', 'pc')
                ->andWhere(
                    'u.name LIKE :search OR u.email LIKE :search OR pc.code LIKE :search OR JSON_GET_TEXT(p.amount, \'amount\') LIKE :search'
                )
                ->setParameter('search', '%' . $search . '%')
            ;
        }

        /** @var Payment[] $result */
        $result = $qb->getQuery()
            ->getResult();

        return $result;
    }

    public function countPendingPayments(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\PaymentRepositoryInterface;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository implements PaymentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * @return array<Payment>
     */
    #[\Override]
    public function findExpiredPendingPayments(\DateTimeImmutable $expirationTime): array
    {
        // Pay-on-place payments have no BLIK code / transfer window - they stay
        // pending until an admin settles them in person, so they must never be
        // auto-expired (which would also auto-cancel their booking via
        // BookingConfirmationSubscriber).
        /** @var array<Payment> $result */
        return $this
            ->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.createdAt < :expirationTime')
            ->andWhere('(p.method IS NULL OR p.method != :payOnPlace)')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->setParameter('expirationTime', $expirationTime)
            ->setParameter('payOnPlace', PaymentMethod::PAY_ON_PLACE->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Payment[]
     */
    #[\Override]
    public function findPendingWithSearch(string $search): array
    {
        $qb = $this
            ->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING);

        if ($search) {
            $qb
                ->join('p.user', 'u')
                ->join('p.paymentCode', 'pc')
                ->andWhere(
                    'u.name LIKE :search OR u.email LIKE :search OR pc.code LIKE :search OR JSON_GET_TEXT(p.amount, \'amount\') LIKE :search',
                )
                ->setParameter('search', '%' . $search . '%');
        }

        /** @var Payment[] $result */
        return $qb->getQuery()->getResult();
    }

    #[\Override]
    public function countPendingPayments(): int
    {
        return (int) $this
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Payment[]
     */
    #[\Override]
    public function findPaidBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var Payment[] $result */
        return $this
            ->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('COALESCE(p.paidAt, p.createdAt) BETWEEN :start AND :end')
            ->setParameter('status', Payment::STATUS_PAID)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }
}

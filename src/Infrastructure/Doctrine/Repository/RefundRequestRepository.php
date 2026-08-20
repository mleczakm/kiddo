<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Entity\Payment;
use App\Entity\RefundRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefundRequest>
 */
class RefundRequestRepository extends ServiceEntityRepository implements RefundRequestRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefundRequest::class);
    }

    #[\Override]
    public function findPendingForPayment(Payment $payment): ?RefundRequest
    {
        return $this->findOneBy([
            'payment' => $payment,
            'status' => RefundRequest::STATUS_PENDING,
        ]);
    }

    /**
     * @return list<RefundRequest>
     */
    #[\Override]
    public function findPendingQueue(): array
    {
        /** @var list<RefundRequest> $result */
        return $this
            ->createQueryBuilder('r')
            ->addSelect('p', 'b', 'u')
            ->join('r.payment', 'p')
            ->join('r.booking', 'b')
            ->leftJoin('r.requestedBy', 'u')
            ->where('r.status = :status')
            ->setParameter('status', RefundRequest::STATUS_PENDING)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function countPending(): int
    {
        return (int) $this
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', RefundRequest::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

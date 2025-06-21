<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return array<Booking>
     */
    public function findExpiredPendingBookings(\DateTimeImmutable $expirationTime): array
    {
        /** @var array<Booking> $bookings */
        $bookings =  $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->andWhere('b.createdAt < :expirationTime')
            ->setParameter('status', Booking::STATUS_PENDING)
            ->setParameter('expirationTime', $expirationTime)
            ->getQuery()
            ->getResult();

        return $bookings;
    }
}

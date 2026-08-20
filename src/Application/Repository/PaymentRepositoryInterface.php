<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Payment;

/**
 * @extends RepositoryInterface<Payment>
 */
interface PaymentRepositoryInterface extends RepositoryInterface
{
    /** @return array<Payment> */
    public function findExpiredPendingPayments(int $expirationMinutes): array;

    /** @return Payment[] */
    public function findPendingWithSearch(string $search): array;

    public function countPendingPayments(): int;

    /** @return Payment[] */
    public function findPaidBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array;
}

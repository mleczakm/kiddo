<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Payment;

/**
 * @extends RepositoryInterface<Payment>
 */
interface PaymentRepositoryInterface extends RepositoryInterface
{
    /**
     * Pending payments created before $expirationTime, excluding pay-on-place
     * payments (which have no code/transfer validity window and are settled in
     * person). This is a naive pre-filter - the caller still applies the
     * working-hours deadline per payment.
     *
     * @return array<Payment>
     */
    public function findExpiredPendingPayments(\DateTimeImmutable $expirationTime): array;

    /** @return Payment[] */
    public function findPendingWithSearch(string $search): array;

    public function countPendingPayments(): int;

    /** @return Payment[] */
    public function findPaidBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array;
}

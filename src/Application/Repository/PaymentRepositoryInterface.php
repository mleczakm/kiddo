<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Payment;
use App\Entity\User;

/**
 * @extends RepositoryInterface<Payment>
 */
interface PaymentRepositoryInterface extends RepositoryInterface
{
    /**
     * Every payment the user still owes money on (pending or expired), newest
     * first. Drives the customer panel's outstanding-payment / quick-pay panel.
     *
     * @return list<Payment>
     */
    public function findUnpaidForUser(User $user): array;

    /**
     * Every payment belonging to the user, newest activity first (paid date,
     * falling back to created date). Drives the panel's billing ledger.
     *
     * @return list<Payment>
     */
    public function findForUser(User $user): array;

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

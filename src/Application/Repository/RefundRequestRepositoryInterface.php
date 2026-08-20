<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Payment;
use App\Entity\RefundRequest;

/**
 * @extends RepositoryInterface<RefundRequest>
 */
interface RefundRequestRepositoryInterface extends RepositoryInterface
{
    public function findPendingForPayment(Payment $payment): ?RefundRequest;

    /**
     * Pending requests, newest first, with payment/booking/user eager-loaded.
     *
     * @return list<RefundRequest>
     */
    public function findPendingQueue(): array;

    public function countPending(): int;
}

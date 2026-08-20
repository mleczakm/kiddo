<?php

declare(strict_types=1);

namespace App\Application\Workflow;

use App\Entity\Payment;

interface PaymentStateMachineInterface
{
    public function can(Payment $payment, string $transition): bool;

    /**
     * @param array<string, mixed> $context
     */
    public function apply(Payment $payment, string $transition, array $context = []): void;
}

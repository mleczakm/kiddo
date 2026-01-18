<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

class SendSettlementConfirmationEmail
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $tenantId,
    ) {}
}

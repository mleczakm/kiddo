<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use App\Entity\Payment;

readonly class SendPaymentNotificationCommand
{
    public function __construct(
        public Payment $payment
    ) {}
}

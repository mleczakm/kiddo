<?php

declare(strict_types=1);

namespace App\Application\Command;

use Brick\Money\Money;

readonly class SendReservationNotification
{
    public function __construct(
        public string $email,
        public string $username,
        public string $paymentCode,
        public Money $paymentAmount
    ) {}
}

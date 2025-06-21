<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Entity\Transfer;

final readonly class MatchPaymentForTransfer
{
    public function __construct(
        public Transfer $transfer
    ) {}
}

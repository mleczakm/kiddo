<?php

namespace App\Application\Command;

use App\Entity\Payment;
use App\Entity\Transfer;

final readonly class MatchPaymentForTransfer
{
    public function __construct(
        public Transfer $transfer
    )
    {
    }
}
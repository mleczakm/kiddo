<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Payment;

interface PaymentFactory
{
    public function create(): Payment;
}

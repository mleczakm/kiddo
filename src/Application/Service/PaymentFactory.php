<?php

namespace App\Application\Service;

use App\Entity\Payment;

interface PaymentFactory
{
    public function create(): Payment;
}
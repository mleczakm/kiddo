<?php

declare(strict_types=1);

namespace App\Entity;

use Brick\Money\Money;

class PaymentFactory
{
    public function generateCode(int $length = 4): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $code;
    }

    public function create(User $user, Money $amount): Payment
    {
        $payment = new Payment($user, $amount);
        new PaymentCode($payment);

        return $payment;
    }
}

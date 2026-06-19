<?php

declare(strict_types=1);

namespace App\Entity;

enum PaymentMethod: string
{
    case ONLINE = 'online';
    case PAY_ON_PLACE = 'pay_on_place';
}

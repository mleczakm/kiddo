<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Order;

enum BuyerType: string
{
    case PRIVATE = 'private';
    case COMPANY = 'company';
}

<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

enum AdjustmentType: string
{
    case SET_PRICE = 'set_price';

    case FIXED_AMOUNT_OFF = 'fixed_amount_off';

    case PERCENTAGE_OFF = 'percentage_off';
}

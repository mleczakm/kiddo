<?php

declare(strict_types=1);

namespace App\Entity;

enum TicketType: string
{
    case ONE_TIME = 'one_time';
    case CARNET_4 = 'carnet_4';
    case MONTHLY = 'monthly';

    /** Ticket types that grant access to a whole span of a series' lessons at once. */
    public function isSeriesScoped(): bool
    {
        return $this === self::CARNET_4 || $this === self::MONTHLY;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

enum WorkshopTicketType: string
{
    case CARNET = 'carnet';
    case ONE_TIME = 'one_time';
    // Możesz dodać inne typy np. INVITE, STAFF_ONLY itd.
}

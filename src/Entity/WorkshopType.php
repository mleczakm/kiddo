<?php

declare(strict_types=1);

namespace App\Entity;

enum WorkshopType: string
{
    case ONE_TIME = 'one_time';
    case WEEKLY = 'weekly';
    // Możesz dodać inne typy np. INVITE, STAFF_ONLY itd.
}
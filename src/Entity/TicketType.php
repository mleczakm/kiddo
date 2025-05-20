<?php

declare(strict_types=1);

namespace App\Entity;

enum TicketType: string
{
    case ONE_TIME = 'one_time';
    case CARNET_4 = 'carnet_4';
}

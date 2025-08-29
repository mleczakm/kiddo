<?php

declare(strict_types=1);

namespace App\Entity;

enum TicketReschedulePolicy: string
{
    case ONETIME_24H_BEFORE = 'onetime_24h_before';
    case UNLIMITED_24H_BEFORE = 'unlimited_24h_before';
    case NOT_ALLOWED = 'not_allowed';
}

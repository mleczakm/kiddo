<?php

declare(strict_types=1);

namespace App\Application\Search;

enum SearchType: string
{
    case Client = 'client';
    case Child = 'child';
    case Booking = 'booking';
    case Lesson = 'lesson';
    case Payment = 'payment';
    case Transfer = 'transfer';
}

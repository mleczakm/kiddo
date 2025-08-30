<?php

declare(strict_types=1);

namespace App\Entity;

enum MessageType: string
{
    case GENERAL = 'general';
    case BOOKING_ISSUE = 'booking_issue';
    case CANCELLATION_REQUEST = 'cancellation_request';
    case RESCHEDULE_REQUEST = 'reschedule_request';
    case REFUND_REQUEST = 'refund_request';
    case COMPLAINT = 'complaint';
    case TECHNICAL_ISSUE = 'technical_issue';
}

<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Kind of event shown in the admin "Ostatnia aktywność" activity feed.
 */
enum ActivityType: string
{
    case USER_REGISTERED = 'user_registered';
    case BOOKING_CREATED = 'booking_created';
    case BOOKING_CANCELLED = 'booking_cancelled';
    case BOOKING_AUTO_CANCELLED = 'booking_auto_cancelled';
    case BOOKING_RESCHEDULED = 'booking_rescheduled';
    case BOOKING_REACTIVATED = 'booking_reactivated';
    case REFUND_REQUESTED = 'refund_requested';
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_MARKED_PAID = 'payment_marked_paid';
    case TRANSFER_UNMATCHED = 'transfer_unmatched';
    case NEWSLETTER_SUBSCRIBED = 'newsletter_subscribed';
    case CUSTOMER_MESSAGE = 'customer_message';
    case USER_PROFILE_UPDATED = 'user_profile_updated';
}

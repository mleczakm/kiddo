<?php

declare(strict_types=1);

namespace App\Application\Service\Waitlist;

enum WaitlistJoinOutcome
{
    /** A new waiting entry was created. */
    case Joined;

    /** The caller already had an active (waiting/offered) entry. */
    case AlreadyQueued;

    /** The lesson still has free seats — book it instead. */
    case SeatAvailable;

    /** Waitlist is off (feature flag or the lesson's own override). */
    case Unavailable;
}

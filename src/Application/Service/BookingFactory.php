<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\TicketOption;
use App\Entity\TicketType;
use App\Entity\User;

class BookingFactory
{
    /**
     * $payment lets OrderPlacementService (Stage 10) share one Payment across
     * several bookings placed in the same order - when omitted, a booking
     * gets its own Payment for just this ticket's price, as before.
     */
    public function createFrom(
        Lesson $lesson,
        TicketOption $ticketOption,
        User $user,
        ?Payment $payment = null,
    ): Booking {
        $payment ??= new Payment($user, $ticketOption->price);

        $booking = match ($ticketOption->type) {
            TicketType::ONE_TIME => new Booking($user, $payment, $lesson),
            TicketType::CARNET_4 => new Booking(
                $user,
                $payment,
                ...$lesson->getSeries()?->getLessonsGte($lesson, 4) ?? [],
            ),
        };
        // Booking's constructor only sets the owning side ($booking->payment) - keep
        // Payment's inverse $bookings collection in sync too, so code reading it back
        // in the same request (e.g. right after OrderPlacementService places several
        // bookings against one shared Payment) sees them without needing a re-fetch.
        $payment->addBooking($booking);

        return $booking;
    }
}

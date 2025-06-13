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
    public function createFrom(Lesson $lesson, TicketOption $ticketOption, User $user): Booking
    {
        $payment = new Payment($user, $ticketOption->price);

        return match ($ticketOption->type) {
            TicketType::ONE_TIME => new Booking($user, $payment, $lesson),
            TicketType::CARNET_4 => new Booking($user, $payment, ... $lesson->getSeries()?->getLessonsGte(
                $lesson,
                4
            ) ?? []),
        };
    }
}

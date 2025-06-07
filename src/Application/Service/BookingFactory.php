<?php

namespace App\Application\Service;

use App\Entity\Booking;
use App\Entity\Carnet;
use App\Entity\Lesson;
use App\Entity\Reservation;
use App\Entity\TicketType;
use App\Entity\User;

class BookingFactory
{
    public function createFrom(Lesson $lesson, TicketType $ticketType, User $user): Booking
    {
        if ($ticketType === TicketType::ONE_TIME) {
            return new Booking($user, $lesson);
        }
        if ($ticketType === TicketType::CARNET_4) {
            // Pobierz serię lekcji, do której należy bieżąca lekcja
            $series = $lesson->getSeries();
            if (!$series) {
                throw new \RuntimeException('Lekcja nie należy do żadnej serii.');
            }
            $allLessons = $series->getLessonsGte($lesson, 4);

            if (count($allLessons) < 4) {
                throw new \RuntimeException('Za mało lekcji w serii, aby utworzyć karnet.');
            }

            $reservations = [];
            foreach ($allLessons as $fetchedLesson) {
                $reservations[] = new Reservation($user, $fetchedLesson);
            }


        }
    }
}


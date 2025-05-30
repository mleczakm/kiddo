<?php

namespace App\Application\Service;

use App\Entity\Lesson;
use App\Entity\Reservation;
use App\Entity\TicketOption;
use App\Entity\TicketType;
use App\Entity\User;

class ReservationsFromTicketTypeFactory
{
    /**
     * @return iterable<Reservation|Carnet> An iterable collection of Reservation and Carnet objects.
     */
    public function createFrom(Lesson $lesson, TicketType $ticketType, User $user): iterable
    {
        if ($ticketType->value === TicketType::ONE_TIME) {
            $reservation = new Reservation($user, $lesson);

            yield $reservation;
        }
        if ($ticketType->value === TicketType::CARNET_4) {
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
                $reservations[] = new Reservation($user, $lesson);
            }

            $carnet = new Carnet($user, $reservations);
            foreach ($selectedLessons as $l) {
                yield new Reservation($user, $l);
            }
        }
    }
}


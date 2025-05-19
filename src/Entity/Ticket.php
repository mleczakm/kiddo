<?php

declare(strict_types=1);

namespace App\Entity;

interface Ticket
{
    public function match(Lesson $lesson): bool;

    public function addReservation(Reservation $reservation): void;
}

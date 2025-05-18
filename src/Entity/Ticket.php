<?php

namespace App\Entity;

interface Ticket
{
    public function match(Lesson $lesson): bool;
    public function addReservation(Reservation $reservation): void;
}
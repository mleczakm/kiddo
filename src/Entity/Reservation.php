<?php

declare(strict_types=1);

namespace App\Entity;

class Reservation
{
    public function __construct(
        public Lesson $lesson,
        public Ticket $ticket,
        public \DateTimeImmutable $date,
    ) {
    }

    public function getLesson(): Lesson
    {
        return $this->lesson;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }
}

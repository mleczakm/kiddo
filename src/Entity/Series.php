<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\Collection;

class Series
{
    public function __construct(
        public WorkshopType $type = WorkshopType::WEEKLY,
        public Collection $lessons,
    ) {
    }

    public function apply(Ticket $ticket)
    {
        $reservations = [];

        foreach ($this->findActiveLessons() as $lesson) {
            if ($ticket->match($lesson)) {
                array_push($reservations, ...$lesson->apply($ticket));
            }
        }

        return $reservations;
    }

    private function findActiveLessons(): iterable
    {
        yield from $this->lessons;
    }
}

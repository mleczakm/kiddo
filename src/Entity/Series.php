<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\Collection;

class Series
{
    public function __construct(
        /** @var Collection<int, Lesson> */
        public Collection $lessons,
        public WorkshopType $type = WorkshopType::WEEKLY,
    ) {
    }

    /**
     * @return list<Reservation>
     */
    public function apply(Ticket $ticket): array
    {
        $reservations = [];

        foreach ($this->findActiveLessons() as $lesson) {
            if ($ticket->match($lesson)) {
                array_push($reservations, ...$lesson->apply($ticket));
            }
        }

        return $reservations;
    }

    /**
     * @return iterable<Lesson>
     */
    private function findActiveLessons(): iterable
    {
        yield from $this->lessons;
    }
}

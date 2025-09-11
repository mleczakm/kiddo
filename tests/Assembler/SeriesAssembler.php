<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Lesson;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\WorkshopType;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @extends EntityAssembler<Series>
 */
class SeriesAssembler extends EntityAssembler
{
    public function withId(string $id): static
    {
        return $this->with('id', $id);
    }

    /**
     * @param array<Lesson> $lessons
     */
    public function withLessons(array $lessons): static
    {
        return $this->with('lessons', $lessons);
    }

    public function withType(WorkshopType $type): static
    {
        return $this->with('type', $type);
    }

    /**
     * @param array<TicketOption> $ticketOptions
     */
    public function withTicketOptions(array $ticketOptions): static
    {
        return $this->with('ticketOptions', $ticketOptions);
    }

    public function assemble(): Series
    {
        /** @var array<Lesson> $lessons */
        $lessons = $this->properties['lessons'] ?? [];
        /** @var WorkshopType $type */
        $type = $this->properties['type'] ?? WorkshopType::WEEKLY;
        /** @var array<TicketOption> $ticketOptions */
        $ticketOptions = $this->properties['ticketOptions'] ?? [];

        // Ensure ticketOptions is a list (sequential numeric keys starting from 0)
        $ticketOptionsList = array_values($ticketOptions);

        $series = new Series(
            lessons: new ArrayCollection($lessons),
            type: $type,
            ticketOptions: $ticketOptionsList
        );

        if (isset($this->properties['id'])) {
            $reflection = new \ReflectionClass($series);
            $property = $reflection->getProperty('id');
            $property->setValue($series, $this->properties['id']);
        }

        return $series;
    }
}

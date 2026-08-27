<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use Brick\Money\Money;

/**
 * @extends EntityAssembler<Lesson>
 */
class LessonAssembler extends EntityAssembler
{
    public function withId(string $id): static
    {
        return $this->with('id', $id);
    }

    public function withStatus(string $status): static
    {
        return $this->with('status', $status);
    }

    public function withVisible(bool $visible): static
    {
        return $this->with('visible', $visible);
    }

    public function withMetadata(LessonMetadata $metadata): static
    {
        return $this->with('metadata', $metadata);
    }

    public function withTitle(string $title): static
    {
        return $this->with('metadata', $this->getMetadata()->withTitle($title));
    }

    public function withLead(string $lead): static
    {
        return $this->with('metadata', $this->getMetadata()->withLead($lead));
    }

    public function withVisualTheme(string $visualTheme): static
    {
        return $this->with('metadata', $this->getMetadata()->withVisualTheme($visualTheme));
    }

    public function withDescription(string $description): static
    {
        return $this->with('metadata', $this->getMetadata()->withDescription($description));
    }

    public function withCapacity(int $capacity): static
    {
        return $this->with('metadata', $this->getMetadata()->withCapacity($capacity));
    }

    public function withSchedule(\DateTimeImmutable $schedule): static
    {
        return $this->with('schedule', $schedule);
    }

    public function withDuration(int $duration): static
    {
        return $this->with('metadata', $this->getMetadata()->withDuration($duration));
    }

    public function withAgeRange(AgeRange $ageRange): static
    {
        return $this->with('metadata', $this->getMetadata()->withAgeRange($ageRange));
    }

    public function withCategory(string $category): static
    {
        return $this->with('metadata', $this->getMetadata()->withCategory($category));
    }

    /**
     * @param array<TicketOption> $ticketOptions
     */
    public function withTicketOptions(array $ticketOptions): static
    {
        return $this->with('ticketOptions', $ticketOptions);
    }

    public function withSeries(Series $series): static
    {
        return $this->with('series', $series);
    }

    #[\Override]
    public function assemble(): Lesson
    {
        /** @var LessonMetadata $metadata */
        $metadata = $this->properties['metadata'] ?? new LessonMetadata(
            title: 'Test Lesson',
            lead: 'Test Lead',
            visualTheme: 'default',
            description: 'Test Description',
            capacity: 10,
            duration: 60,
            ageRange: new AgeRange(5, 10),
            category: 'test',
        );

        /** @var \DateTimeImmutable $schedule */
        $schedule = $this->properties['schedule'] ?? new \DateTimeImmutable('+1 day');

        $lesson = new Lesson($metadata, $schedule);

        if (isset($this->properties['id'])) {
            $reflection = new \ReflectionClass($lesson);
            $property = $reflection->getProperty('id');
            $property->setValue($lesson, $this->properties['id']);
        }

        if (isset($this->properties['status'])) {
            $reflection = new \ReflectionClass($lesson);
            $property = $reflection->getProperty('status');
            $property->setValue($lesson, $this->properties['status']);
        }

        if (is_bool($this->properties['visible'] ?? null)) {
            $lesson->visible = $this->properties['visible'];
        }

        if (isset($this->properties['ticketOptions'])) {
            $reflection = new \ReflectionClass($lesson);
            $property = $reflection->getProperty('ticketOptions');
            $property->setValue($lesson, $this->properties['ticketOptions']);
        } else {
            // Add a default ticket option if none provided
            $reflection = new \ReflectionClass($lesson);
            $property = $reflection->getProperty('ticketOptions');
            $property->setValue($lesson, [new TicketOption(
                TicketType::ONE_TIME,
                Money::of(50, 'PLN'),
                'Bilet jednorazowy',
                TicketReschedulePolicy::ONETIME_24H_BEFORE,
            )]);
        }

        if (isset($this->properties['series'])) {
            $reflection = new \ReflectionClass($lesson);
            $property = $reflection->getProperty('series');
            $property->setValue($lesson, $this->properties['series']);
        }

        return $lesson;
    }

    private function getMetadata(): LessonMetadata
    {
        $metadata = $this->normalizeMetadata($this->properties['metadata'] ?? null);
        if ($metadata !== null) {
            return $metadata;
        }

        return new LessonMetadata(
            title: 'Test Lesson',
            lead: 'Test Lead',
            visualTheme: 'default',
            description: 'Test Description',
            capacity: 10,
            duration: 60,
            ageRange: new AgeRange(5, 10),
            category: 'test',
        );
    }

    private function normalizeMetadata(mixed $metadata): ?LessonMetadata
    {
        return $metadata instanceof LessonMetadata ? $metadata : null;
    }
}

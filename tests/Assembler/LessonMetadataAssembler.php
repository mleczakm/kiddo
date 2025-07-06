<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\AgeRange;
use App\Entity\LessonMetadata;

class LessonMetadataAssembler
{
    public function __construct(
        private string $title,
        private string $lead,
        private string $visualTheme,
        private string $description,
        private int $capacity,
        private \DateTimeImmutable $schedule,
        private int $duration,
        private AgeRange $ageRange,
        private string $category = 'Category'
    ) {}

    public static function new(): self
    {
        return new self(
            title: 'Default Title',
            lead: 'Default Lead',
            visualTheme: 'Default Visual Theme',
            description: 'Default Description',
            capacity: 30,
            duration: 60, // Default duration in minutes
            category: 'Default Category',
            schedule: new \DateTimeImmutable('now'),
            ageRange: AgeRangeAssembler::new()->assemble(),
        );
    }

    public function withTitle(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;
        return $clone;
    }

    public function withLead(string $lead): self
    {
        $clone = clone $this;
        $clone->lead = $lead;
        return $clone;
    }

    public function withVisualTheme(string $visualTheme): self
    {
        $clone = clone $this;
        $clone->visualTheme = $visualTheme;
        return $clone;
    }

    public function withDescription(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;
        return $clone;
    }

    public function withCapacity(int $capacity): self
    {
        $clone = clone $this;
        $clone->capacity = $capacity;
        return $clone;
    }

    public function withSchedule(\DateTimeImmutable $schedule): self
    {
        $clone = clone $this;
        $clone->schedule = $schedule;
        return $clone;
    }

    public function withDuration(int $duration): self
    {
        $clone = clone $this;
        $clone->duration = $duration;
        return $clone;
    }

    public function withAgeRange(AgeRange $ageRange): self
    {
        $clone = clone $this;
        $clone->ageRange = $ageRange;
        return $clone;
    }

    public function withCategory(string $category): self
    {
        $clone = clone $this;
        $clone->category = $category;
        return $clone;
    }

    public function assemble(): LessonMetadata
    {
        return new LessonMetadata(
            $this->title,
            $this->lead,
            $this->visualTheme,
            $this->description,
            $this->capacity,
            $this->schedule,
            $this->duration,
            $this->ageRange,
            $this->category
        );
    }
}

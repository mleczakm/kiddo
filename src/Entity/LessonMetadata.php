<?php

declare(strict_types=1);

namespace App\Entity;

final class LessonMetadata
{
    public function __construct(
        public string $title,
        public string $lead,
        public string $visualTheme,
        public string $description,
        public int $capacity,
        public \DateTimeImmutable $schedule,
        public int $duration,
        public AgeRange $ageRange,
        public string $category,
    ) {
    }
}

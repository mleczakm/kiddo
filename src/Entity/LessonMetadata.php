<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class LessonMetadata
{
    public function __construct(
        #[ORM\Column(type: 'string', length: 255)]
        public string $title,
        #[ORM\Column(type: 'text')]
        public string $lead,
        #[ORM\Column(type: 'string', length: 255)]
        public string $visualTheme,
        #[ORM\Column(type: 'text')]
        public string $description,
        #[ORM\Column(type: 'integer')]
        public int $capacity,
        #[ORM\Column(type: 'datetime_immutable')]
        public \DateTimeImmutable $schedule,
        #[ORM\Column(type: 'integer')]
        public int $duration,
        #[ORM\Embedded(class: AgeRange::class)]
        public AgeRange $ageRange,
        #[ORM\Column(type: 'string', length: 50)]
        public string $category,
    ) {}

    public function withTitle(string $title): self
    {
        $new = clone $this;
        $new->title = $title;
        return $new;
    }

    public function withLead(string $lead): self
    {
        $new = clone $this;
        $new->lead = $lead;
        return $new;
    }

    public function withVisualTheme(string $visualTheme): self
    {
        $new = clone $this;
        $new->visualTheme = $visualTheme;
        return $new;
    }

    public function withDescription(string $description): self
    {
        $new = clone $this;
        $new->description = $description;
        return $new;
    }

    public function withCapacity(int $capacity): self
    {
        $new = clone $this;
        $new->capacity = $capacity;
        return $new;
    }

    public function withSchedule(\DateTimeImmutable $schedule): self
    {
        $new = clone $this;
        $new->schedule = $schedule;
        return $new;
    }

    public function withDuration(int $duration): self
    {
        $new = clone $this;
        $new->duration = $duration;
        return $new;
    }

    public function withAgeRange(AgeRange $ageRange): self
    {
        $new = clone $this;
        $new->ageRange = $ageRange;
        return $new;
    }

    public function withCategory(string $category): self
    {
        $new = clone $this;
        $new->category = $category;
        return $new;
    }
}

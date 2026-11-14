<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\AgeRange;
use App\Entity\LessonMetadata;

class LessonMetadataAssembler
{
    private string $title = 'Default Title';
    private string $lead = 'Default Lead';
    private string $visualTheme = 'Default Visual Theme';
    private string $description = 'Default Description';
    private int $capacity = 30;
    private int $duration = 60;
    private string $category = 'Default Category';
    private AgeRange $ageRange;

    private function __construct()
    {
        $this->ageRange = AgeRangeAssembler::new()->assemble();
    }

    public static function new(): self
    {
        return new self();
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
            $this->duration,
            $this->ageRange,
            $this->category,
        );
    }
}

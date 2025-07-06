<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\AgeRange;

class AgeRangeAssembler
{
    public function __construct(
        private int $min = 5,
        private ?int $max = 12
    ) {}

    public static function new(): self
    {
        return new self();
    }

    public function withMin(int $min): self
    {
        $clone = clone $this;
        $clone->min = $min;
        return $clone;
    }

    public function withMax(?int $max): self
    {
        $clone = clone $this;
        $clone->max = $max;
        return $clone;
    }

    public function assemble(): AgeRange
    {
        return new AgeRange($this->min, $this->max);
    }
}

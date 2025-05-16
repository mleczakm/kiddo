<?php

declare(strict_types=1);

namespace App\Entity;

class AgeRange
{
    public function __construct(
        public readonly int $min,
        public readonly ?int $max,
    ) {
    }
}

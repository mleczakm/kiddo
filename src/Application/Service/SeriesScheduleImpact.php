<?php

declare(strict_types=1);

namespace App\Application\Service;

final readonly class SeriesScheduleImpact
{
    public function __construct(
        public int $create = 0,
        public int $hide = 0,
        public int $delete = 0,
    ) {}
}

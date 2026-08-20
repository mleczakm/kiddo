<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Series;

/**
 * @extends RepositoryInterface<Series>
 */
interface SeriesRepositoryInterface extends RepositoryInterface
{
    /** @return array<int, Series> */
    public function findInRange(\DateTimeImmutable $start, \DateTimeImmutable $end, bool $showCancelled = false): array;

    /** @return array<int, Series> */
    public function findActive(): array;
}

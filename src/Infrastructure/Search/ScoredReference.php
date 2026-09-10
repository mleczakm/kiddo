<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchReference;

/**
 * A search reference paired with the integer priority used to order it in the
 * global-search {@see \Ds\PriorityQueue} (same scale as {@see PostgresGlobalSearchQuery}).
 */
final readonly class ScoredReference
{
    public function __construct(
        public SearchReference $reference,
        public int $priority,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Search;

use Ds\PriorityQueue;

interface GlobalSearchQuery
{
    /**
     * @return PriorityQueue<SearchReference>
     */
    public function search(string $query, int $limit = 15): PriorityQueue;
}

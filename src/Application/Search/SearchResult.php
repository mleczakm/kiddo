<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchResult
{
    public function __construct(
        public SearchReference $reference,
        public string $title,
        public string $subtitle,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchReference
{
    public function __construct(
        public SearchType $type,
        public string $id,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Service;

final readonly class PostSeoInput
{
    public function __construct(
        public ?string $seoTitle,
        public ?string $seoDescription,
        public ?string $canonicalUrl,
        public bool $robotsIndex,
        public bool $robotsFollow,
    ) {}
}

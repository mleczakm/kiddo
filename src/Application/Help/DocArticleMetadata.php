<?php

declare(strict_types=1);

namespace App\Application\Help;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class DocArticleMetadata
{
    /**
     * @param list<string> $keywords
     * @param list<string> $related
     */
    public function __construct(
        public string $role,
        public ?string $primaryRoute,
        public array $keywords,
        public array $related,
        public \DateTimeImmutable $updatedAt,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Service;

final readonly class PostSocialInput
{
    public function __construct(
        public ?string $socialTitle,
        public ?string $socialDescription,
    ) {}
}

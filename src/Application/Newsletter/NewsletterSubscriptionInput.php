<?php

declare(strict_types=1);

namespace App\Application\Newsletter;

final readonly class NewsletterSubscriptionInput
{
    public function __construct(
        public string $email,
        public bool $spam = false,
    ) {}
}

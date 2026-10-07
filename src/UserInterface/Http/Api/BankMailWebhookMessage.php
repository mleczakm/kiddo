<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

final readonly class BankMailWebhookMessage
{
    public function __construct(
        public string $id,
        public string $subject,
        public string $content,
        public \DateTimeImmutable $receivedAt,
    ) {}
}

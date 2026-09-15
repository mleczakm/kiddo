<?php

declare(strict_types=1);

namespace App\Application\Command;

readonly class SendWebPushNotification
{
    public function __construct(
        public int $userId,
        public string $title,
        public ?string $body = null,
        public ?string $url = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Command;

readonly class SendLoginNotification
{
    public function __construct(
        public string $email
    ) {
    }
}

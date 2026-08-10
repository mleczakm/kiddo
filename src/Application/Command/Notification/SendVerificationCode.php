<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

final readonly class SendVerificationCode
{
    public function __construct(
        public string $email,
        public string $code,
    ) {}
}

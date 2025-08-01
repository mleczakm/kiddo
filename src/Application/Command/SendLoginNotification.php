<?php

declare(strict_types=1);

namespace App\Application\Command;

readonly class SendLoginNotification
{
    public string $email;

    public function __construct(string $email)
    {
        $this->email = mb_strtolower($email);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Notification;

interface NotificationSenderInterface
{
    public function send(string $email, string $subject, string $content): void;
}

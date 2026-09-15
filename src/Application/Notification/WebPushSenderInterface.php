<?php

declare(strict_types=1);

namespace App\Application\Notification;

use App\Entity\User;

interface WebPushSenderInterface
{
    public function sendToUser(User $user, string $title, ?string $body, ?string $url): void;
}

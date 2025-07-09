<?php

declare(strict_types=1);

namespace App\Application\Command\Notification;

use App\Entity\User;

readonly class NewUser
{
    public function __construct(
        public User $user
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Entity\AwaitingPayment;
use App\Entity\Lesson;
use App\Entity\User;

final class SaveAwaitingPayment
{
    public function __construct(
        public readonly User $user,
        public readonly AwaitingPayment $awaitingPayment,
        public readonly Lesson $lesson
    ) {
    }
}


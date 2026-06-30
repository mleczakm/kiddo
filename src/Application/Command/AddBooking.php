<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class AddBooking
{
    public function __construct(
        public int $userId,
        public string $lessonId,
        public string $ticketType,
        public ?string $childId,
        public string $paymentCode,
    ) {}
}

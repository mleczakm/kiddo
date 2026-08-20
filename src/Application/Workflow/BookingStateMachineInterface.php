<?php

declare(strict_types=1);

namespace App\Application\Workflow;

use App\Entity\Booking;

interface BookingStateMachineInterface
{
    public function can(Booking $booking, string $transition): bool;

    /**
     * @param array<string, mixed> $context
     */
    public function apply(Booking $booking, string $transition, array $context = []): void;
}

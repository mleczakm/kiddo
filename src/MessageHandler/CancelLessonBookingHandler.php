<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\UseCase\CancelBookingOccurrence;
use App\Message\CancelLessonBooking;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CancelLessonBookingHandler
{
    public function __construct(
        private readonly CancelBookingOccurrence $cancelBookingOccurrence,
    ) {}

    public function __invoke(CancelLessonBooking $command): void
    {
        ($this->cancelBookingOccurrence)(
            $command->getBookingId(),
            $command->getLessonId(),
            $command->getCancelledBy()->getId() ?? throw new \LogicException('Cancelling user has no id'),
            $command->getReason(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\UseCase\CancelAndRequestRefund;
use App\Message\RefundLessonBooking;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefundLessonBookingHandler
{
    public function __construct(
        private readonly CancelAndRequestRefund $cancelAndRequestRefund,
    ) {}

    public function __invoke(RefundLessonBooking $command): void
    {
        ($this->cancelAndRequestRefund)(
            $command->getBookingId(),
            $command->getLessonId(),
            $command->getRefundedBy()->getId() ?? throw new \LogicException('Refunding user has no id'),
            $command->getReason(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Command\Notification\SendBookingCancellationNotificationCommand;
use App\Entity\Booking;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\Event;

readonly class BookingWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.booking.transition.cancel' => 'onBookingCancelled',
        ];
    }

    public function onBookingCancelled(Event $event): void
    {
        $booking = $event->getSubject();

        if (! $booking instanceof Booking) {
            return;
        }

        // Only send notification if booking was cancelled due to non-payment
        if ($booking->getStatus() === Booking::STATUS_CANCELLED) {
            $this->messageBus->dispatch(new SendBookingCancellationNotificationCommand($booking));
        }
    }
}

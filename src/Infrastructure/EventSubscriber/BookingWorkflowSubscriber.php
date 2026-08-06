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

        // This listener only runs for the "cancel" transition, so the transition is
        // already known to be happening here. Note: the workflow component dispatches
        // this "transition" event *before* updating the subject's marking (status), so
        // $booking->getStatus() is still the pre-transition value at this point — it
        // cannot be used to gate this notification.
        $this->messageBus->dispatch(new SendBookingCancellationNotificationCommand($booking));
    }
}

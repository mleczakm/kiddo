<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Entity\Booking;
use Logdash\Logdash;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

final readonly class LogdashBookingWorkflowMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ?Logdash $logdash = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.booking.transition' => 'onBookingTransition',
        ];
    }

    public function onBookingTransition(Event $event): void
    {
        if ($this->logdash === null) {
            return;
        }

        $subject = $event->getSubject();
        if (! $subject instanceof Booking) {
            return;
        }

        $transition = $event->getTransition()?->getName();
        $this->logdash->metrics()
            ->mutate('workflow.booking.transitions.total', 1);
        $this->logdash->metrics()
            ->mutate('workflow.booking.transitions.' . $transition, 1);
    }
}

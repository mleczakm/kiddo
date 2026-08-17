<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Entity\Booking;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

final readonly class SentryBookingWorkflowMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MetricsRecorderInterface $metrics,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.booking.transition' => 'onBookingTransition',
        ];
    }

    public function onBookingTransition(Event $event): void
    {
        $subject = $event->getSubject();
        if (! $subject instanceof Booking) {
            return;
        }

        $transition = $event->getTransition()?->getName();
        if ($transition === null) {
            return;
        }

        $this->metrics->count('workflow.booking.transitions.total', 1);
        $this->metrics->count('workflow.booking.transitions.' . $transition, 1);
    }
}

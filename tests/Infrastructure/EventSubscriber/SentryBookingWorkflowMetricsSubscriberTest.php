<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Infrastructure\EventSubscriber\SentryBookingWorkflowMetricsSubscriber;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use App\Tests\Assembler\BookingAssembler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Transition;

#[Group('unit')]
class SentryBookingWorkflowMetricsSubscriberTest extends TestCase
{
    private MetricsRecorderInterface&MockObject $metrics;

    private SentryBookingWorkflowMetricsSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->metrics = $this->createMock(MetricsRecorderInterface::class);
        $this->subscriber = new SentryBookingWorkflowMetricsSubscriber($this->metrics);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SentryBookingWorkflowMetricsSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('workflow.booking.transition', $events);
        $this->assertEquals('onBookingTransition', $events['workflow.booking.transition']);
    }

    public function testOnBookingTransitionTracksMetrics(): void
    {
        $booking = BookingAssembler::new()
            ->assemble();

        $transition = new Transition('cancel', ['pending'], ['cancelled']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($booking);
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->exactly(2))
            ->method('count')
            ->with(
                $this->logicalOr('workflow.booking.transitions.total', 'workflow.booking.transitions.cancel'),
                1,
            );

        $this->subscriber->onBookingTransition($event);
    }

    public function testOnBookingTransitionDoesNothingWhenSubjectIsNotBooking(): void
    {
        $transition = new Transition('cancel', ['pending'], ['cancelled']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn(new \stdClass());
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->never())
            ->method('count');

        $this->subscriber->onBookingTransition($event);
    }

    public function testOnBookingTransitionTracksDifferentTransitionTypes(): void
    {
        $booking = BookingAssembler::new()
            ->assemble();

        $transition = new Transition('confirm', ['pending'], ['active']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($booking);
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->exactly(2))
            ->method('count')
            ->with(
                $this->logicalOr('workflow.booking.transitions.total', 'workflow.booking.transitions.confirm'),
                1,
            );

        $this->subscriber->onBookingTransition($event);
    }
}

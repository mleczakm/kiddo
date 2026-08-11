<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Entity\Booking;
use App\Infrastructure\EventSubscriber\LogdashBookingWorkflowMetricsSubscriber;
use App\Tests\Assembler\UserAssembler;
use Logdash\Logdash;
use Logdash\Metrics\BaseMetrics;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Transition;

#[Group('unit')]
class LogdashBookingWorkflowMetricsSubscriberTest extends TestCase
{
    private Logdash|MockObject $logdash;

    private MockObject $metrics;

    private LogdashBookingWorkflowMetricsSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->metrics = $this->createMock(BaseMetrics::class);
        $this->logdash = $this->createMock(Logdash::class);
        $this->logdash->method('metrics')
            ->willReturn($this->metrics);

        $this->subscriber = new LogdashBookingWorkflowMetricsSubscriber($this->logdash);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = LogdashBookingWorkflowMetricsSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('workflow.booking.transition', $events);
        $this->assertEquals('onBookingTransition', $events['workflow.booking.transition']);
    }

    public function testOnBookingTransitionTracksMetrics(): void
    {
        $user = UserAssembler::new()->assemble();
        $booking = new Booking($user, null);

        $transition = new Transition('cancel', ['pending'], ['cancelled']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($booking);
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->exactly(2))
            ->method('mutate');

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
            ->method('mutate');

        $this->subscriber->onBookingTransition($event);
    }

    public function testOnBookingTransitionDoesNothingWhenLogdashIsNull(): void
    {
        $subscriber = new LogdashBookingWorkflowMetricsSubscriber(null);

        $user = UserAssembler::new()->assemble();
        $booking = new Booking($user, null);

        $transition = new Transition('cancel', ['pending'], ['cancelled']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($booking);
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->never())
            ->method('mutate');

        $subscriber->onBookingTransition($event);
    }

    public function testOnBookingTransitionTracksDifferentTransitionTypes(): void
    {
        $user = UserAssembler::new()->assemble();
        $booking = new Booking($user, null);

        $transition = new Transition('confirm', ['pending'], ['confirmed']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($booking);
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->exactly(2))
            ->method('mutate');

        $this->subscriber->onBookingTransition($event);
    }
}

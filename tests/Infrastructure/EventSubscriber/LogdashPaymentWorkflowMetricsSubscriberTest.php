<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Entity\Payment;
use App\Infrastructure\EventSubscriber\LogdashPaymentWorkflowMetricsSubscriber;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Logdash\Logdash;
use Logdash\Metrics\BaseMetrics;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Transition;

#[Group('unit')]
class LogdashPaymentWorkflowMetricsSubscriberTest extends TestCase
{
    private Logdash|MockObject $logdash;

    private MockObject $metrics;

    private LogdashPaymentWorkflowMetricsSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->metrics = $this->createMock(BaseMetrics::class);
        $this->logdash = $this->createMock(Logdash::class);
        $this->logdash->method('metrics')
            ->willReturn($this->metrics);

        $this->subscriber = new LogdashPaymentWorkflowMetricsSubscriber($this->logdash);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = LogdashPaymentWorkflowMetricsSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('workflow.payment.transition', $events);
        $this->assertEquals('onPaymentTransition', $events['workflow.payment.transition']);
    }

    public function testOnPaymentTransitionTracksMetrics(): void
    {
        $user = UserAssembler::new()->assemble();
        $payment = new Payment($user, Money::of('100.00', 'PLN'));

        $transition = new Transition('pay', ['pending'], ['paid']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($payment);
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->exactly(2))
            ->method('mutate');

        $this->subscriber->onPaymentTransition($event);
    }

    public function testOnPaymentTransitionDoesNothingWhenSubjectIsNotPayment(): void
    {
        $transition = new Transition('pay', ['pending'], ['paid']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn(new \stdClass());
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->never())
            ->method('mutate');

        $this->subscriber->onPaymentTransition($event);
    }

    public function testOnPaymentTransitionDoesNothingWhenLogdashIsNull(): void
    {
        $subscriber = new LogdashPaymentWorkflowMetricsSubscriber(null);

        $user = UserAssembler::new()->assemble();
        $payment = new Payment($user, Money::of('100.00', 'PLN'));

        $transition = new Transition('pay', ['pending'], ['paid']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($payment);
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->never())
            ->method('mutate');

        $subscriber->onPaymentTransition($event);
    }

    public function testOnPaymentTransitionTracksDifferentTransitionTypes(): void
    {
        $user = UserAssembler::new()->assemble();
        $payment = new Payment($user, Money::of('100.00', 'PLN'));

        $transition = new Transition('expire', ['pending'], ['expired']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($payment);
        $event->method('getTransition')
            ->willReturn($transition);

        $this->metrics->expects($this->exactly(2))
            ->method('mutate');

        $this->subscriber->onPaymentTransition($event);
    }
}

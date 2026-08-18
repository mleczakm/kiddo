<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Infrastructure\EventSubscriber\SentryPaymentWorkflowMetricsSubscriber;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use App\Tests\Assembler\PaymentAssembler;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Transition;

#[Group('unit')]
class SentryPaymentWorkflowMetricsSubscriberTest extends TestCase
{
    private MetricsRecorderInterface&MockObject $metrics;

    private SentryPaymentWorkflowMetricsSubscriber $subscriber;

    #[\Override]
    protected function setUp(): void
    {
        $this->metrics = $this->createMock(MetricsRecorderInterface::class);
        $this->subscriber = new SentryPaymentWorkflowMetricsSubscriber($this->metrics);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SentryPaymentWorkflowMetricsSubscriber::getSubscribedEvents();

        static::assertArrayHasKey('workflow.payment.transition', $events);
        static::assertSame('onPaymentTransition', $events['workflow.payment.transition']);
    }

    public function testOnPaymentTransitionTracksCountersAndAmountOnPay(): void
    {
        $payment = PaymentAssembler::new()->withAmount(Money::of('100.00', 'PLN'))->assemble();

        $transition = new Transition('pay', ['pending'], ['paid']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')->willReturn($payment);
        $event->method('getTransition')->willReturn($transition);

        $this->metrics->expects($this->exactly(2))->method('count');
        $this->metrics->expects($this->once())->method('distribution')->with('payments.amount', 100.0, [
            'currency' => 'PLN',
        ]);

        $this->subscriber->onPaymentTransition($event);
    }

    public function testOnPaymentTransitionDoesNothingWhenSubjectIsNotPayment(): void
    {
        $transition = new Transition('pay', ['pending'], ['paid']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')->willReturn(new \stdClass());
        $event->method('getTransition')->willReturn($transition);

        $this->metrics->expects($this->never())->method('count');
        $this->metrics->expects($this->never())->method('distribution');

        $this->subscriber->onPaymentTransition($event);
    }

    public function testOnPaymentTransitionSkipsAmountForNonPayTransitions(): void
    {
        $payment = PaymentAssembler::new()->assemble();

        $transition = new Transition('expire', ['pending'], ['expired']);
        $event = $this->createMock(Event::class);
        $event->method('getSubject')->willReturn($payment);
        $event->method('getTransition')->willReturn($transition);

        $this->metrics->expects($this->exactly(2))->method('count');
        $this->metrics->expects($this->never())->method('distribution');

        $this->subscriber->onPaymentTransition($event);
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Entity\Payment;
use App\Infrastructure\Sentry\MetricsRecorderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

final readonly class SentryPaymentWorkflowMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MetricsRecorderInterface $metrics,
    ) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.payment.transition' => 'onPaymentTransition',
        ];
    }

    public function onPaymentTransition(Event $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof Payment) {
            return;
        }

        $transition = $event->getTransition()?->getName();
        if ($transition === null) {
            return;
        }

        $this->metrics->count('workflow.payment.transitions.total', 1);
        $this->metrics->count('workflow.payment.transitions.' . $transition, 1);

        if ($transition === 'pay') {
            $amount = $subject->getAmount();
            $this->metrics->distribution('payments.amount', $amount->getAmount()->toFloat(), [
                'currency' => $amount->getCurrency()->getCurrencyCode(),
            ]);
        }
    }
}

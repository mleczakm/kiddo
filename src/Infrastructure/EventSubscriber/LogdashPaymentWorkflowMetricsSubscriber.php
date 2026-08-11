<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Entity\Payment;
use Logdash\Logdash;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

final readonly class LogdashPaymentWorkflowMetricsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ?Logdash $logdash = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.payment.transition' => 'onPaymentTransition',
        ];
    }

    public function onPaymentTransition(Event $event): void
    {
        if ($this->logdash === null) {
            return;
        }

        $subject = $event->getSubject();
        if (! $subject instanceof Payment) {
            return;
        }

        $transition = $event->getTransition()?->getName();
        $this->logdash->metrics()
            ->mutate('workflow.payment.transitions.total', 1);
        $this->logdash->metrics()
            ->mutate('workflow.payment.transitions.' . $transition, 1);
    }
}

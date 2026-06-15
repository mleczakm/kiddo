<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Service\PlatformBillingService;
use App\Entity\Payment;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

readonly class PlatformBillingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PlatformBillingService $platformBillingService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.payment.transition.pay' => 'onPaymentPaid',
        ];
    }

    public function onPaymentPaid(Event $event): void
    {
        $payment = $event->getSubject();

        if (! $payment instanceof Payment) {
            return;
        }

        // Add 2% commission to current due
        $this->platformBillingService->addCommissionToCurrentDue($payment->getAmount());
    }
}

<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Payment;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

class PaymentWorkflowSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.payment.transition.pay' => 'onPay',
            'workflow.payment.transition.fail' => 'onFail',
            'workflow.payment.transition.refund' => 'onRefund',
            'workflow.payment.transition.expire' => 'onExpire',
        ];
    }

    public function onPay(Event $event): void
    {
        /** @var Payment $payment */
        $payment = $event->getSubject();
        $payment->markAsPaid();
    }

    public function onFail(Event $event): void
    {
        // Handle failure logic if needed
    }

    public function onRefund(Event $event): void
    {
        // Handle refund logic if needed
    }

    public function onExpire(Event $event): void
    {
        // Handle expiration logic if needed
    }
}

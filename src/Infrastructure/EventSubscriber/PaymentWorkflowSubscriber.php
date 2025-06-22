<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Entity\Payment;
use App\Application\Command\Notification\SendPaymentNotificationCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\Event;

readonly class PaymentWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $messageBus
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

        // Dispatch message with payment ID to handle notifications asynchronously
        $this->messageBus->dispatch(new SendPaymentNotificationCommand($payment));
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Workflow\BookingStateMachineInterface;
use App\Entity\Payment;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

class BookingConfirmationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BookingStateMachineInterface $bookingStateMachine,
    ) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.payment.completed.pay' => 'onPaymentCompleted',
            'workflow.payment.completed.expire' => 'onPaymentExpired',
        ];
    }

    public function onPaymentCompleted(Event $event): void
    {
        $payment = $event->getSubject();

        if (!$payment instanceof Payment) {
            return;
        }

        // Only process if payment was just marked as paid
        if ($payment->getStatus() !== Payment::STATUS_PAID) {
            return;
        }

        // Get all bookings associated with this payment
        $bookings = $payment->getBookings();

        foreach ($bookings as $booking) {
            if (!$this->bookingStateMachine->can($booking, 'confirm')) {
                continue;
            }

            $this->bookingStateMachine->apply($booking, 'confirm');
        }
    }

    public function onPaymentExpired(Event $event): void
    {
        $payment = $event->getSubject();

        if (!$payment instanceof Payment) {
            return;
        }

        // Only process if payment was just marked as paid
        if ($payment->getStatus() !== Payment::STATUS_EXPIRED) {
            return;
        }

        // Get all bookings associated with this payment
        $bookings = $payment->getBookings();

        foreach ($bookings as $booking) {
            if (!$this->bookingStateMachine->can($booking, 'cancel')) {
                continue;
            }

            $this->bookingStateMachine->apply($booking, 'cancel');
        }
    }
}

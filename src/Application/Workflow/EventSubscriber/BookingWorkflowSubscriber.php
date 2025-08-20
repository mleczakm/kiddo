<?php

declare(strict_types=1);

namespace App\Application\Workflow\EventSubscriber;

use App\Entity\Payment;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\WorkflowInterface;

class BookingWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly WorkflowInterface $bookingStateMachine,
    ) {}

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

        if (! $payment instanceof Payment) {
            return;
        }

        // Only process if payment was just marked as paid
        if ($payment->getStatus() !== Payment::STATUS_PAID) {
            return;
        }

        // Get all bookings associated with this payment
        $bookings = $payment->getBookings();

        foreach ($bookings as $booking) {
            if ($this->bookingStateMachine->can($booking, 'confirm')) {
                $this->bookingStateMachine->apply($booking, 'confirm');
            }
        }
    }

    public function onPaymentExpired(Event $event): void
    {
        $payment = $event->getSubject();

        if (! $payment instanceof Payment) {
            return;
        }

        // Only process if payment was just marked as paid
        if ($payment->getStatus() !== Payment::STATUS_EXPIRED) {
            return;
        }

        // Get all bookings associated with this payment
        $bookings = $payment->getBookings();

        foreach ($bookings as $booking) {
            if ($this->bookingStateMachine->can($booking, 'cancel')) {
                $this->bookingStateMachine->apply($booking, 'cancel');
            }
        }
    }
}

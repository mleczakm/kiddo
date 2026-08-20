<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Domain\Commerce\Order\CustomerOrder;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

/**
 * Stage 6 of the commerce rollout plan: Payment paid -> Order paid, for
 * payments that were dual-written to an order (Stage 5). Booking
 * confirmation itself is unaffected - BookingConfirmationSubscriber already
 * confirms every booking under the payment regardless of order presence.
 * Legacy payments without an order (payment.orderId === null) are untouched
 * and keep going through the existing subscribers only.
 */
class OrderPaymentPropagationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.payment.completed.pay' => 'onPaymentCompleted',
        ];
    }

    public function onPaymentCompleted(Event $event): void
    {
        $payment = $event->getSubject();

        if (!$payment instanceof Payment) {
            return;
        }

        if ($payment->getStatus() !== Payment::STATUS_PAID) {
            return;
        }

        $orderId = $payment->getOrderId();
        if ($orderId === null) {
            return;
        }

        $order = $this->em->find(CustomerOrder::class, $orderId);
        if ($order === null || $order->getStatus() === CustomerOrder::STATUS_PAID) {
            return;
        }

        // No explicit flush: $order is a managed entity, and every caller of
        // the payment workflow (MatchPaymentForTransferHandler, the manual
        // assignment modal, ...) already flushes once after applying the
        // transition - matching BookingConfirmationSubscriber's precedent for
        // the same event, rather than flushing again here mid-transition.
        $order->markPaid();
    }
}

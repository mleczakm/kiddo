<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Service\Commerce\PromotionRedemptionService;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

final readonly class OrderPaymentExpirySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PromotionRedemptionService $promotionRedemptions,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return ['workflow.payment.completed.expire' => 'onExpired'];
    }

    public function onExpired(Event $event): void
    {
        $payment = $event->getSubject();
        if (!$payment instanceof Payment || $payment->getOrderId() === null) {
            return;
        }

        $order = $this->em->find(CustomerOrder::class, $payment->getOrderId());
        if (!$order instanceof CustomerOrder) {
            return;
        }

        $order->markExpired();
        $this->promotionRedemptions->releaseForOrder($order->getId());
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Application\Service\BookingFactory;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\TicketType;
use App\Entity\User;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Turns a list of item selections into bookings sharing one Payment, and
 * (usually) a CustomerOrder with one OrderLine per item - the single place
 * both the fast-reservation path and the cart's CheckoutCart place an order
 * (Stage 10 of the commerce rollout plan). Persists everything it builds;
 * the caller still owns flush().
 */
final class OrderPlacementService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookingFactory $bookingFactory,
    ) {}

    /**
     * @param list<OrderItemSelection> $items
     */
    public function place(
        User $user,
        string $source,
        string $paymentCode,
        array $items,
        bool $writeOrder = true,
    ): OrderPlacementResult {
        if ($items === []) {
            throw new \InvalidArgumentException('Cannot place an order with no items.');
        }

        $currency = $items[0]->ticketOption->price->getCurrency()->getCurrencyCode();
        $totalMinor = 0;
        foreach ($items as $item) {
            $totalMinor += $item->ticketOption->price->getMinorAmount()->toInt();
        }

        $payment = new Payment($user, Money::ofMinor($totalMinor, $currency));
        new PaymentCode($payment, $paymentCode);

        $orderId = new Ulid();
        $bookings = [];
        $orderLines = [];
        $subtotalMinor = 0;

        foreach ($items as $item) {
            $booking = $this->bookingFactory->createFrom($item->lesson, $item->ticketOption, $user, $payment);
            if ($item->participant !== null) {
                $booking->setChild($item->participant);
            }
            $bookings[] = $booking;

            $finalPriceMinor = $item->ticketOption->price->getMinorAmount()->toInt();
            $basePriceMinor = $item->quote->basePriceMinor ?? $finalPriceMinor;
            $subtotalMinor += $basePriceMinor;

            if (!$writeOrder) {
                continue;
            }

            $isSeries = $item->ticketOption->type === TicketType::CARNET_4;
            $line = new OrderLine(
                id: new Ulid(),
                orderId: $orderId,
                lessonId: $isSeries ? null : $item->lesson->getId(),
                seriesId: $isSeries ? $item->lesson->getSeries()?->getId() : null,
                ticketType: $item->ticketOption->type->value,
                title: $item->lesson->getMetadata()->title,
                scheduleDescription: $item->lesson->schedule->format('Y-m-d H:i'),
                participantId: $item->participant?->getId(),
                basePriceMinor: $basePriceMinor,
                finalPriceMinor: $finalPriceMinor,
                currency: $currency,
                pricingQuoteHash: $item->quote?->quoteHash,
                bookingId: $booking->getId(),
                pricingSnapshotJson: $item->quote === null
                    ? null
                    : [
                        'adjustments' => array_map(static fn($a): array => [
                            'ruleId' => $a->ruleId,
                            'type' => $a->type->value,
                            'deltaMinor' => $a->deltaMinor,
                            'label' => $a->label,
                        ], $item->quote->adjustments),
                        'rejectedRuleReasons' => $item->quote->rejectedRuleReasons,
                    ],
            );
            $booking->setOrderLineId($line->getId());
            $orderLines[] = $line;
        }

        $order = null;
        if ($writeOrder) {
            $order = new CustomerOrder(
                id: $orderId,
                orderNumber: self::orderNumberPrefix($source) . '-' . $orderId->toBase32(),
                customerId: $user->getId() ?? throw new \LogicException(
                    'Cannot place an order for an unpersisted user.',
                ),
                status: CustomerOrder::STATUS_PLACED,
                currency: $currency,
                subtotalMinor: $subtotalMinor,
                discountTotalMinor: $subtotalMinor - $totalMinor,
                totalMinor: $totalMinor,
                placedAt: new \DateTimeImmutable(),
                expiresAt: null,
                checkoutKey: (string) new Ulid(),
                source: $source,
            );
            $payment->setOrderId($order->getId());

            $this->em->persist($order);
            foreach ($orderLines as $line) {
                $this->em->persist($line);
            }
        }

        foreach ($bookings as $booking) {
            $this->em->persist($booking);
        }

        return new OrderPlacementResult($order, $orderLines, $bookings, $payment);
    }

    private static function orderNumberPrefix(string $source): string
    {
        return match ($source) {
            CustomerOrder::SOURCE_CART => 'CT',
            CustomerOrder::SOURCE_FAST_TRACK => 'FT',
            CustomerOrder::SOURCE_ADMIN => 'AD',
            CustomerOrder::SOURCE_CHAT => 'CH',
            default => 'OR',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Application\Service\BookingFactory;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\Subscription;
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
        private readonly PromotionRedemptionService $promotionRedemptions,
        private readonly OrderCapacity $capacity,
        private readonly OrderLineFactory $orderLines,
    ) {}

    /**
     * @param list<OrderItemSelection> $items
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Math\Exception\NumberFormatException
     * @throws \Brick\Math\Exception\RoundingNecessaryException
     * @throws \Brick\Money\Exception\UnknownCurrencyException
     * @throws \Doctrine\DBAL\Exception
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Random\RandomException
     */
    public function place(
        User $user,
        string $source,
        string $paymentCode,
        array $items,
        ?OrderPlacementOptions $options = null,
    ): OrderPlacementResult {
        $options ??= OrderPlacementOptions::standard();
        $place =
            /**
             * @throws \Brick\Math\Exception\MathException
             * @throws \Brick\Math\Exception\NumberFormatException
             * @throws \Brick\Math\Exception\RoundingNecessaryException
             * @throws \Brick\Money\Exception\UnknownCurrencyException
             * @throws \InvalidArgumentException
             * @throws \LogicException
             * @throws \Doctrine\DBAL\Exception
             * @throws \Random\RandomException
             */
            fn() => $this->placeTransactionally($user, $source, $paymentCode, $items, $options);

        if ($this->em->getConnection()->isTransactionActive()) {
            return $place();
        }

        return $this->em->wrapInTransaction($place);
    }

    /**
     * @param list<OrderItemSelection> $items
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Math\Exception\NumberFormatException
     * @throws \Brick\Math\Exception\RoundingNecessaryException
     * @throws \Brick\Money\Exception\UnknownCurrencyException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Doctrine\DBAL\Exception
     * @throws \Random\RandomException
     */
    private function placeTransactionally(
        User $user,
        string $source,
        string $paymentCode,
        array $items,
        OrderPlacementOptions $options,
    ): OrderPlacementResult {
        if ($items === []) {
            throw new \InvalidArgumentException('Cannot place an order with no items.');
        }

        $this->capacity->lockAndValidate($items);

        $currency = $items[0]->ticketOption->price->getCurrency()->getCurrencyCode();
        $totalMinor = array_sum(array_map($this->lineAmount(...), $items));

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

            $this->attachMonthlySubscription($item, $user, $booking, $payment);

            $bookings[] = $booking;

            $finalPriceMinor = $item->ticketOption->price->getMinorAmount()->toInt();
            $basePriceMinor = $item->quote->basePriceMinor ?? $finalPriceMinor;
            $subtotalMinor += $basePriceMinor;

            if (!$options->writeOrder) {
                continue;
            }

            $orderLines[] = $this->orderLines->create($orderId, $item, $booking, $currency);
        }

        $order = null;
        if ($options->writeOrder) {
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
                buyerType: $options->buyerDetails->type->value,
                taxIdentifier: $options->buyerDetails->taxIdentifier,
            );
            $payment->setOrderId($order->getId());

            $this->em->persist($order);
            foreach ($orderLines as $line) {
                $this->em->persist($line);
            }
            $this->promotionRedemptions->reserve($order->getId(), $order->getCustomerId(), $orderLines);
        }

        foreach ($bookings as $booking) {
            $this->em->persist($booking);
        }

        $this->em->flush();

        return new OrderPlacementResult($order, $orderLines, $bookings, $payment);
    }

    /** @throws \Brick\Math\Exception\MathException */
    private function lineAmount(OrderItemSelection $item): int
    {
        return $item->ticketOption->price->getMinorAmount()->toInt();
    }

    /**
     * @throws \Brick\Math\Exception\MathException
     * @throws \InvalidArgumentException
     */
    private function attachMonthlySubscription(
        OrderItemSelection $item,
        User $user,
        Booking $booking,
        Payment $payment,
    ): void {
        if ($item->ticketOption->type !== TicketType::MONTHLY) {
            return;
        }
        $series = $item->lesson->getSeries() ?? throw new \InvalidArgumentException(
            'A monthly ticket requires a series.',
        );
        $subscription = new Subscription(
            $user,
            $series,
            $item->ticketOption->price->getMinorAmount()->toInt(),
            $item->participant,
        );
        $this->em->persist($subscription);
        $subscription->markCharged($item->lesson->schedule);
        $booking->setSubscriptionId($subscription->getId());
        $payment->setDescription(sprintf(
            '%s · %s',
            $item->lesson->getMetadata()->title,
            $item->lesson->schedule->format('m.Y'),
        ));
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

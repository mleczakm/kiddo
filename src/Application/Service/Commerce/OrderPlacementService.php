<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Application\Service\BookingFactory;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\Subscription;
use App\Entity\TicketType;
use App\Entity\User;
use Brick\Money\Money;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
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
    ) {}

    /**
     * @param list<OrderItemSelection> $items
     */
    public function place(
        User $user,
        string $source,
        string $paymentCode,
        array $items,
        ?OrderPlacementOptions $options = null,
    ): OrderPlacementResult {
        $options ??= OrderPlacementOptions::standard();
        $place = fn(): OrderPlacementResult => $this->placeTransactionally(
            $user,
            $source,
            $paymentCode,
            $items,
            $options,
        );

        if ($this->em->getConnection()->isTransactionActive()) {
            return $place();
        }

        return $this->em->wrapInTransaction($place);
    }

    /** @param list<OrderItemSelection> $items */
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

        $this->lockAndValidateCapacity($items);

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

            $this->attachMonthlySubscription($item, $user, $booking, $payment);

            $bookings[] = $booking;

            $finalPriceMinor = $item->ticketOption->price->getMinorAmount()->toInt();
            $basePriceMinor = $item->quote->basePriceMinor ?? $finalPriceMinor;
            $subtotalMinor += $basePriceMinor;

            if (!$options->writeOrder) {
                continue;
            }

            $orderLines[] = $this->createOrderLine($orderId, $item, $booking, $currency);
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

    /** @throws \Brick\Math\Exception\MathException */
    private function createOrderLine(
        Ulid $orderId,
        OrderItemSelection $item,
        Booking $booking,
        string $currency,
    ): OrderLine {
        $finalPriceMinor = $item->ticketOption->price->getMinorAmount()->toInt();
        $basePriceMinor = $item->quote->basePriceMinor ?? $finalPriceMinor;
        $isSeries = $item->ticketOption->type->isSeriesScoped();
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
        return $line;
    }

    /** @param list<OrderItemSelection> $items */
    private function lockAndValidateCapacity(array $items): void
    {
        /** @var array<string, array{lesson: Lesson, required: int}> $requirements */
        $requirements = [];
        foreach ($items as $item) {
            $occurrences = match ($item->ticketOption->type) {
                TicketType::CARNET_4 => $item->lesson->getSeries()?->getLessonsGte($item->lesson, 4) ?? [],
                TicketType::MONTHLY => BookingFactory::monthLessons($item->lesson),
                TicketType::ONE_TIME => [$item->lesson],
            };
            foreach ($occurrences as $lesson) {
                $id = (string) $lesson->getId();
                $requirements[$id] ??= ['lesson' => $lesson, 'required' => 0];
                ++$requirements[$id]['required'];
            }
        }

        if ($requirements === []) {
            return;
        }

        $ids = array_map(static fn($requirement): string => $requirement['lesson']
            ->getId()
            ->toRfc4122(), array_values($requirements));
        /** @var list<Lesson> $lockedLessons */
        $lockedLessons = $this->em
            ->createQueryBuilder()
            ->select('lesson')
            ->from(Lesson::class, 'lesson')
            ->where('lesson.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('lesson.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
        $lessons = [];
        foreach ($lockedLessons as $lesson) {
            $lessons[(string) $lesson->getId()] = $lesson;
        }
        $occupied = $this->occupiedSeats($ids);

        foreach ($requirements as $id => $requirement) {
            $lesson = $lessons[$id] ?? null;
            if (!$lesson instanceof Lesson) {
                throw new \InvalidArgumentException(sprintf('Lesson %s no longer exists.', $id));
            }
            if (
                !$lesson->future()
                || $lesson->status !== 'active'
                || !$lesson->isPubliclyVisible()
                || ($lesson->getMetadata()->capacity - (int) ($occupied[$lesson->getId()->toRfc4122()] ?? 0))
                    < $requirement['required']
            ) {
                throw new CapacityExceededException(sprintf('Lesson %s has insufficient capacity.', $id));
            }
        }
    }

    /** @param list<string> $lessonIds
     * @return array<string, int>
     */
    private function occupiedSeats(array $lessonIds): array
    {
        /** @var array<string, int|string> $rows */
        $rows = $this->em->getConnection()->fetchAllKeyValue(<<<'SQL'
                SELECT occurrence.lesson_id, COUNT(*)
            FROM booking_occurrence occurrence
            INNER JOIN booking b ON b.id = occurrence.booking_id
            WHERE occurrence.lesson_id IN (:lesson_ids)
              AND occurrence.status IN ('reserved', 'confirmed')
              AND b.status IN ('pending', 'active', 'waiting_approval')
            GROUP BY occurrence.lesson_id
            SQL, [
            'lesson_ids' => $lessonIds,
        ], ['lesson_ids' => ArrayParameterType::STRING]);
        return array_map(static fn(int|string $count): int => (int) $count, $rows);
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

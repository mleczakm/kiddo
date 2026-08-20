<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Domain\Commerce\Pricing\PriceQuote;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\TicketOption;
use App\Entity\TicketType;
use App\Entity\User;
use Symfony\Component\Uid\Ulid;

/**
 * Builds the commerce-order dual-write for the fast (single ticket, single
 * booking) reservation path (see PlaceSingleReservation). Pure mapping, no
 * persistence - the caller decides whether/how to persist the result.
 */
final readonly class FastTrackOrderFactory
{
    /**
     * @return array{0: CustomerOrder, 1: OrderLine}
     */
    public function create(
        Booking $booking,
        Payment $payment,
        Lesson $lesson,
        TicketOption $ticketOption,
        User $user,
        ?PriceQuote $quote = null,
    ): array {
        $orderId = new Ulid();
        // The payment (not the ticket option) is the source of truth for what was actually
        // charged - they hold the same value today, but only one of them is meant to.
        $priceMinor = $payment->getAmount()->getMinorAmount()->toInt();
        $currency = $payment->getAmount()->getCurrency()->getCurrencyCode();
        $basePriceMinor = $quote?->basePriceMinor ?? $priceMinor;
        $now = new \DateTimeImmutable();

        $order = new CustomerOrder(
            id: $orderId,
            orderNumber: 'FT-' . $orderId->toBase32(),
            customerId: $user->getId() ?? throw new \LogicException(
                'Cannot dual-write an order for an unpersisted user.',
            ),
            status: CustomerOrder::STATUS_PLACED,
            currency: $currency,
            subtotalMinor: $basePriceMinor,
            discountTotalMinor: $basePriceMinor - $priceMinor,
            totalMinor: $priceMinor,
            placedAt: $now,
            expiresAt: null,
            checkoutKey: (string) new Ulid(),
            source: CustomerOrder::SOURCE_FAST_TRACK,
        );

        $isSeries = $ticketOption->type === TicketType::CARNET_4;

        $line = new OrderLine(
            id: new Ulid(),
            orderId: $orderId,
            lessonId: $isSeries ? null : $lesson->getId(),
            seriesId: $isSeries ? $lesson->getSeries()?->getId() : null,
            ticketType: $ticketOption->type->value,
            title: $lesson->getMetadata()->title,
            scheduleDescription: $lesson->schedule->format('Y-m-d H:i'),
            participantId: $booking->getChild()?->getId(),
            basePriceMinor: $basePriceMinor,
            finalPriceMinor: $priceMinor,
            currency: $currency,
            pricingQuoteHash: $quote?->quoteHash,
            bookingId: $booking->getId(),
            pricingSnapshotJson: $quote === null
                ? null
                : [
                    'adjustments' => array_map(static fn($a): array => [
                        'ruleId' => $a->ruleId,
                        'type' => $a->type->value,
                        'deltaMinor' => $a->deltaMinor,
                        'label' => $a->label,
                    ], $quote->adjustments),
                    'rejectedRuleReasons' => $quote->rejectedRuleReasons,
                ],
        );

        return [$order, $line];
    }
}

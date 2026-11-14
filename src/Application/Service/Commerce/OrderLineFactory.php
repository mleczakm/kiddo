<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use Symfony\Component\Uid\Ulid;

final class OrderLineFactory
{
    /** @throws \Brick\Math\Exception\MathException */
    public function create(Ulid $orderId, OrderItemSelection $item, Booking $booking, string $currency): OrderLine
    {
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
}

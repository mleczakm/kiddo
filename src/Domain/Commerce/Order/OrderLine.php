<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Order;

use Symfony\Component\Uid\Ulid;

/**
 * One purchased ticket within an order. Holds a snapshot of what was
 * bought and at what price - a later change to the source lesson/series
 * price must never alter an already-placed line. References to the
 * lesson/series/child/booking are plain scalar ids rather than Doctrine
 * relations, deliberately: this keeps the commerce domain decoupled from
 * the existing booking entities (see CustomerOrder's docblock).
 */
final class OrderLine
{
    public function __construct(
        private readonly Ulid $id,
        private readonly Ulid $orderId,
        private readonly ?Ulid $lessonId,
        private readonly ?Ulid $seriesId,
        private readonly string $ticketType,
        private readonly string $title,
        private readonly ?string $scheduleDescription,
        private readonly ?Ulid $participantId,
        private readonly int $basePriceMinor,
        private readonly int $finalPriceMinor,
        private readonly string $currency,
        private readonly ?string $pricingQuoteHash,
        private ?Ulid $bookingId,
        /**
         * The full PriceQuote at the moment this line was placed - every
         * adjustment applied/rejected, for audit purposes (Stage 8 of the
         * commerce rollout plan). $pricingQuoteHash alone identifies the
         * quote; this is what it actually contained.
         *
         * @var array{adjustments: list<array{ruleId: string, type: string, deltaMinor: int, label: string}>, rejectedRuleReasons: list<string>}|null
         */
        private readonly ?array $pricingSnapshotJson = null,
    ) {}

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getOrderId(): Ulid
    {
        return $this->orderId;
    }

    public function getLessonId(): ?Ulid
    {
        return $this->lessonId;
    }

    public function getSeriesId(): ?Ulid
    {
        return $this->seriesId;
    }

    public function getTicketType(): string
    {
        return $this->ticketType;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getScheduleDescription(): ?string
    {
        return $this->scheduleDescription;
    }

    public function getParticipantId(): ?Ulid
    {
        return $this->participantId;
    }

    public function getBasePriceMinor(): int
    {
        return $this->basePriceMinor;
    }

    public function getFinalPriceMinor(): int
    {
        return $this->finalPriceMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getPricingQuoteHash(): ?string
    {
        return $this->pricingQuoteHash;
    }

    public function getBookingId(): ?Ulid
    {
        return $this->bookingId;
    }

    /**
     * @return array{adjustments: list<array{ruleId: string, type: string, deltaMinor: int, label: string}>, rejectedRuleReasons: list<string>}|null
     */
    public function getPricingSnapshotJson(): ?array
    {
        return $this->pricingSnapshotJson;
    }
}

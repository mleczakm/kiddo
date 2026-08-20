<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\PriceQuote;
use App\Domain\Commerce\Pricing\PricingContext;
use App\Entity\Lesson;
use Brick\Money\Money;

/**
 * Builds a PricingContext from a lesson/ticket-type selection and resolves a
 * PriceQuote for it (Stage 8 of the commerce rollout plan). Shared by the
 * fast-reservation modal (to display a quote and its hash) and
 * PlaceSingleReservation (to re-resolve the same quote at confirm time and
 * check it hasn't gone stale). No rule source exists yet (Stage 9), so the
 * candidate rule list is always empty - the quote always equals $basePrice
 * today, but the hash still changes with time/user/ticket-type inputs.
 */
final readonly class PriceQuoter
{
    public function __construct(
        private PricingEngine $engine,
    ) {}

    public function quote(?int $userId, Lesson $lesson, string $ticketType, Money $basePrice): PriceQuote
    {
        $context = new PricingContext(
            userId: $userId,
            lessonId: $lesson->getId(),
            seriesId: $lesson->getSeries()?->getId(),
            ticketType: $ticketType,
            evaluationTime: new \DateTimeImmutable(),
        );

        return $this->engine->quote(
            $context,
            $basePrice->getMinorAmount()->toInt(),
            $basePrice->getCurrency()->getCurrencyCode(),
            candidateRules: [],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Application\Repository\PricingRuleRepositoryInterface;
use App\Domain\Commerce\Pricing\PriceQuote;
use App\Domain\Commerce\Pricing\PricingContext;
use App\Entity\Lesson;
use Brick\Money\Money;

/**
 * Builds a PricingContext from a lesson/ticket-type selection and resolves a
 * PriceQuote for it (Stage 8 of the commerce rollout plan). Shared by the
 * fast-reservation modal (to display a quote and its hash),
 * PlaceSingleReservation (to re-resolve the same quote at confirm time and
 * check it hasn't gone stale), and the cart use cases (Stage 10) - the only
 * caller that ever passes a non-null $promotionCode, since the fast path has
 * no UI to enter one. Candidate rules come from every active PricingRule
 * (Stage 9) - there is still no usage-tracking infrastructure (Stage 12), so
 * usage/per-user counts are always empty.
 */
final readonly class PriceQuoter
{
    public function __construct(
        private PricingEngine $engine,
        private PricingRuleRepositoryInterface $pricingRuleRepository,
    ) {}

    public function quote(
        ?int $userId,
        Lesson $lesson,
        string $ticketType,
        Money $basePrice,
        ?\DateTimeImmutable $at = null,
        ?string $promotionCode = null,
    ): PriceQuote {
        $context = new PricingContext(
            userId: $userId,
            lessonId: $lesson->getId(),
            seriesId: $lesson->getSeries()?->getId(),
            ticketType: $ticketType,
            evaluationTime: $at ?? new \DateTimeImmutable(),
            promotionCode: $promotionCode,
        );

        return $this->engine->quote(
            $context,
            $basePrice->getMinorAmount()->toInt(),
            $basePrice->getCurrency()->getCurrencyCode(),
            candidateRules: $this->pricingRuleRepository->findActive(),
        );
    }
}

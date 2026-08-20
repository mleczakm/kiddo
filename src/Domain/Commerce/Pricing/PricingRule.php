<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

use Symfony\Component\Uid\Ulid;

/**
 * A configurable pricing rule. Pure domain type for Stage 7 (shadow mode) -
 * not persisted yet, and nothing can create one (no admin UI until Stage 9).
 * Every scope field left null is unconstrained on that dimension, i.e. the
 * rule matches any value there.
 */
final readonly class PricingRule
{
    public function __construct(
        public Ulid $id,
        public string $name,
        public AdjustmentType $adjustmentType,
        /**
         * Interpreted per $adjustmentType: the new price in minor units for
         * SET_PRICE, the amount to subtract in minor units for
         * FIXED_AMOUNT_OFF, or a 0-100 integer percentage for PERCENTAGE_OFF.
         */
        public int $adjustmentValue,
        public int $priority = 0,
        public bool $stackable = true,
        public ?string $exclusivityGroup = null,
        public ?int $userId = null,
        public ?Ulid $seriesId = null,
        public ?Ulid $lessonId = null,
        public ?string $ticketType = null,
        public ?string $promotionCode = null,
        public ?\DateTimeImmutable $validFrom = null,
        public ?\DateTimeImmutable $validUntil = null,
        public ?int $usageLimit = null,
        public ?int $perUserLimit = null,
    ) {}

    /**
     * How narrowly this rule is targeted - used to pick between multiple
     * applicable SET_PRICE rules (the most specific one wins). Each
     * identity-scope dimension set to a non-null value adds one point; the
     * validity window and usage limits constrain when or how often a rule
     * applies, not what it targets, so they don't count here.
     */
    public function specificity(): int
    {
        return (
            (int) ($this->userId !== null)
            + (int) ($this->seriesId !== null)
            + (int) ($this->lessonId !== null)
            + (int) ($this->ticketType !== null)
            + (int) ($this->promotionCode !== null)
        );
    }

    public function appliesTo(PricingContext $context, int $timesUsedGlobally = 0, int $timesUsedByUser = 0): bool
    {
        if ($this->userId !== null && $this->userId !== $context->userId) {
            return false;
        }

        if ($this->seriesId !== null && ($context->seriesId === null || !$this->seriesId->equals($context->seriesId))) {
            return false;
        }

        if ($this->lessonId !== null && !$this->lessonId->equals($context->lessonId)) {
            return false;
        }

        if ($this->ticketType !== null && $this->ticketType !== $context->ticketType) {
            return false;
        }

        if (
            $this->promotionCode !== null
            && ($context->promotionCode === null || strcasecmp($this->promotionCode, $context->promotionCode) !== 0)
        ) {
            return false;
        }

        if ($this->validFrom !== null && $context->evaluationTime < $this->validFrom) {
            return false;
        }

        if ($this->validUntil !== null && $context->evaluationTime > $this->validUntil) {
            return false;
        }

        if ($this->usageLimit !== null && $timesUsedGlobally >= $this->usageLimit) {
            return false;
        }

        if ($this->perUserLimit !== null && $timesUsedByUser >= $this->perUserLimit) {
            return false;
        }

        return true;
    }
}

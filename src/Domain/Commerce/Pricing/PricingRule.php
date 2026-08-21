<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

use Symfony\Component\Uid\Ulid;

/**
 * A configurable pricing rule. Persisted since Stage 9 (pricing
 * administration) - admin-editable via public properties, matching how
 * other entities in this codebase (e.g. Lesson) are mutated directly rather
 * than through setters. Only $id is immutable; everything else can change
 * over the rule's lifetime. Every scope field left null is unconstrained on
 * that dimension, i.e. the rule matches any value there.
 *
 * Deliberately never referenced live from a placed order - OrderLine stores
 * a snapshot (pricingSnapshotJson) of what a rule did at quote time, not a
 * relation to the rule itself, so editing or disabling a rule can never
 * change the financial record of an order already placed.
 */
final class PricingRule
{
    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_DISABLED = 'disabled';

    public function __construct(
        public readonly Ulid $id,
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
        public string $status = self::STATUS_ACTIVE,
        public int $version = 1,
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {}

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function disable(): void
    {
        $this->status = self::STATUS_DISABLED;
    }

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

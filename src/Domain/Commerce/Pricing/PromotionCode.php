<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

use Symfony\Component\Uid\Ulid;

/**
 * A customer-facing discount code, separate from PricingRule so a promotion
 * ("WELCOME10") reads distinctly from the 4-character bank-transfer payment
 * code - the two are unrelated and easy to confuse by name alone. One or
 * more PricingRule instances reference a PromotionCode's normalized $code
 * via PricingRule::$promotionCode. Not persisted yet (see PricingRule); no
 * UI can create or redeem one until the cart lands (Stage 10+).
 */
final readonly class PromotionCode
{
    public function __construct(
        public Ulid $id,
        public string $code,
        public bool $active = true,
        public ?\DateTimeImmutable $validFrom = null,
        public ?\DateTimeImmutable $validUntil = null,
        public ?int $usageLimit = null,
        public ?int $perUserLimit = null,
    ) {}

    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function isActiveAt(\DateTimeImmutable $moment): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->validFrom !== null && $moment < $this->validFrom) {
            return false;
        }

        if ($this->validUntil !== null && $moment > $this->validUntil) {
            return false;
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Pricing;

use Symfony\Component\Uid\Ulid;

/**
 * Everything a PricingRule's scope can be matched against. Pure input data -
 * no Doctrine/Messenger dependencies (see Stage 7 of the commerce rollout
 * plan). $promotionCode is what the customer entered, if anything; there is
 * no UI to enter one yet (that's the cart, Stage 10+), so it is always null
 * on the fast-reservation path today.
 */
final readonly class PricingContext
{
    public function __construct(
        public ?int $userId,
        public Ulid $lessonId,
        public ?Ulid $seriesId,
        public string $ticketType,
        public \DateTimeImmutable $evaluationTime,
        public ?string $promotionCode = null,
    ) {}
}

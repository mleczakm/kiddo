<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Pricing;

use App\Application\Service\Pricing\PricingEngine;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingContext;
use App\Domain\Commerce\Pricing\PricingRule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

abstract class PricingEngineTestCase extends TestCase
{
    protected PricingEngine $engine;

    protected Ulid $lessonId;

    #[\Override]
    protected function setUp(): void
    {
        $this->engine = new PricingEngine();
        $this->lessonId = new Ulid();
    }

    protected function context(
        ?int $userId = 1,
        ?Ulid $seriesId = null,
        string $ticketType = 'one_time',
        ?string $promotionCode = null,
        ?\DateTimeImmutable $at = null,
    ): PricingContext {
        return new PricingContext(
            userId: $userId,
            lessonId: $this->lessonId,
            seriesId: $seriesId,
            ticketType: $ticketType,
            evaluationTime: $at ?? new \DateTimeImmutable('2026-06-15 12:00:00'),
            promotionCode: $promotionCode,
        );
    }

    /**
     * @param array{
     *     priority?: int, stackable?: bool, exclusivityGroup?: string, userId?: int,
     *     lessonId?: Ulid, seriesId?: Ulid, ticketType?: string, promotionCode?: string,
     *     validFrom?: \DateTimeImmutable, validUntil?: \DateTimeImmutable,
     *     usageLimit?: int, perUserLimit?: int, name?: string
     * } $options
     */
    protected function rule(AdjustmentType $type, int $value, array $options = []): PricingRule
    {
        $name = $options['name'] ?? 'Test rule';
        unset($options['name']);

        return new PricingRule(new Ulid(), $name, $type, $value, ...$options);
    }
}

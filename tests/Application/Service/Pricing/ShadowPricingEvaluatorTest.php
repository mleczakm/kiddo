<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Pricing;

use App\Application\Service\Pricing\ShadowPricingEvaluator;
use App\Domain\Commerce\Pricing\PricingContext;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class ShadowPricingEvaluatorTest extends KernelTestCase
{
    public function testAgreesWithTheLegacyPriceAndLogsNothingWhenNoRulesExist(): void
    {
        // There is no rule source yet (Stage 9), so the engine always sees
        // an empty candidate list and must agree with the legacy price.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $evaluator = new ShadowPricingEvaluator(new \App\Application\Service\Pricing\PricingEngine(), $logger);

        $evaluator->evaluate(
            new PricingContext(
                userId: 1,
                lessonId: new Ulid(),
                seriesId: null,
                ticketType: 'one_time',
                evaluationTime: new \DateTimeImmutable(),
            ),
            Money::of('95.50', 'PLN'),
        );
    }

    public function testIsWiredIntoTheContainer(): void
    {
        self::bootKernel();

        static::assertInstanceOf(
            ShadowPricingEvaluator::class,
            self::getContainer()->get(ShadowPricingEvaluator::class),
        );
    }
}

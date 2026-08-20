<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Pricing;

use App\Application\Service\Pricing\ShadowPricingEvaluator;
use App\Domain\Commerce\Pricing\PriceQuote;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class ShadowPricingEvaluatorTest extends KernelTestCase
{
    private function quote(int $finalPriceMinor): PriceQuote
    {
        return new PriceQuote(
            basePriceMinor: 9_550,
            finalPriceMinor: $finalPriceMinor,
            currency: 'PLN',
            adjustments: [],
            rejectedRuleReasons: [],
            quoteHash: 'test-hash',
            computedAt: new \DateTimeImmutable(),
        );
    }

    public function testLogsNothingWhenTheQuoteAgreesWithTheLegacyPrice(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $evaluator = new ShadowPricingEvaluator($logger);
        $evaluator->evaluate($this->quote(9_550), 9_550);
    }

    public function testLogsAWarningWhenTheQuoteDivergesFromTheLegacyPrice(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('warning')
            ->with(
                'Shadow pricing diverged from the legacy TicketOption price',
                static::callback(
                    static fn(array $context): bool => (
                        $context['legacyPriceMinor'] === 9_550
                        && $context['shadowPriceMinor'] === 8_000
                        && $context['quoteHash'] === 'test-hash'
                    ),
                ),
            );

        $evaluator = new ShadowPricingEvaluator($logger);
        $evaluator->evaluate($this->quote(8_000), 9_550);
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

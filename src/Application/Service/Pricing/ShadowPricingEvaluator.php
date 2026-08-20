<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\PriceQuote;
use Psr\Log\LoggerInterface;

/**
 * Stage 7 of the commerce rollout plan, now only used while dynamic_pricing
 * is disabled (Stage 8 activated it for the fast path by default): logs a
 * divergence between an already-resolved PriceQuote and the legacy
 * TicketOption price, without ever charging the new result.
 */
final readonly class ShadowPricingEvaluator
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function evaluate(PriceQuote $quote, int $legacyPriceMinor): void
    {
        if ($quote->finalPriceMinor === $legacyPriceMinor) {
            return;
        }

        $this->logger->warning('Shadow pricing diverged from the legacy TicketOption price', [
            'legacyPriceMinor' => $legacyPriceMinor,
            'shadowPriceMinor' => $quote->finalPriceMinor,
            'currency' => $quote->currency,
            'quoteHash' => $quote->quoteHash,
        ]);
    }
}

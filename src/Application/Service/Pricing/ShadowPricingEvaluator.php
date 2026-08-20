<?php

declare(strict_types=1);

namespace App\Application\Service\Pricing;

use App\Domain\Commerce\Pricing\PricingContext;
use Brick\Money\Money;
use Psr\Log\LoggerInterface;

/**
 * Stage 7 of the commerce rollout plan: runs the new pricing engine
 * alongside every fast reservation and logs any divergence from the legacy
 * TicketOption price, without ever charging the new result. There is no
 * rule source yet - PricingRule persistence and admin CRUD are Stage 9 - so
 * the candidate rule list is always empty and the two prices should always
 * agree; this only proves the plumbing runs cleanly in production ahead of
 * Stage 8 wiring real rules into it.
 */
final readonly class ShadowPricingEvaluator
{
    public function __construct(
        private PricingEngine $engine,
        private LoggerInterface $logger,
    ) {}

    public function evaluate(PricingContext $context, Money $legacyPrice): void
    {
        $legacyPriceMinor = $legacyPrice->getMinorAmount()->toInt();
        $currency = $legacyPrice->getCurrency()->getCurrencyCode();

        $quote = $this->engine->quote($context, $legacyPriceMinor, $currency, candidateRules: []);

        if ($quote->finalPriceMinor === $legacyPriceMinor) {
            return;
        }

        $this->logger->warning('Shadow pricing diverged from the legacy TicketOption price', [
            'legacyPriceMinor' => $legacyPriceMinor,
            'shadowPriceMinor' => $quote->finalPriceMinor,
            'currency' => $currency,
            'quoteHash' => $quote->quoteHash,
            'lessonId' => (string) $context->lessonId,
            'ticketType' => $context->ticketType,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Commerce\Pricing\PriceQuote;

/**
 * Thrown by PlaceSingleReservation when dynamic_pricing is enabled and the
 * quote hash the caller confirmed against no longer matches what the price
 * currently resolves to (Stage 8 of the commerce rollout plan). Carries the
 * fresh quote so the caller can show the updated price and ask the user to
 * confirm again, instead of silently charging a different amount than what
 * was displayed.
 */
final class PriceQuoteMismatchException extends \RuntimeException
{
    public function __construct(
        public readonly PriceQuote $currentQuote,
    ) {
        parent::__construct('The price quote used to confirm this booking is no longer current.');
    }
}

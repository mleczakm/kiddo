<?php

declare(strict_types=1);

namespace App\Application\Command;

final readonly class AddBooking
{
    public function __construct(
        public int $userId,
        public string $lessonId,
        public string $ticketType,
        public ?string $childId,
        public string $paymentCode,
        /**
         * The PriceQuote::$quoteHash the caller last displayed to the user
         * (see PriceQuoter). Null means the caller doesn't participate in the
         * quote/reconfirm flow (e.g. the chat booking tool) - the current
         * price is charged with no staleness check.
         */
        public ?string $expectedQuoteHash = null,
    ) {}
}

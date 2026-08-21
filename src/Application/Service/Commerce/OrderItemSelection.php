<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Domain\Commerce\Pricing\PriceQuote;
use App\Entity\Child;
use App\Entity\Lesson;
use App\Entity\TicketOption;

/**
 * One ticket to place, already resolved and priced by the caller
 * (PlaceSingleReservation or the cart's CheckoutCart) - OrderPlacementService
 * (Stage 10 of the commerce rollout plan) only turns selections into
 * bookings/order lines, it never looks anything up itself.
 */
final readonly class OrderItemSelection
{
    public function __construct(
        public Lesson $lesson,
        public TicketOption $ticketOption,
        public ?Child $participant,
        /**
         * Null when dynamic pricing wasn't applied (shadow mode) - the
         * resulting OrderLine then has no adjustment audit trail, matching
         * FastTrackOrderFactory's previous behavior.
         */
        public ?PriceQuote $quote,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Order\OrderLine;
use App\Entity\Booking;
use App\Entity\Payment;

final readonly class OrderPlacementResult
{
    /**
     * @param list<OrderLine> $orderLines
     * @param list<Booking> $bookings
     */
    public function __construct(
        /**
         * Null when placed with $writeOrder: false (see OrderPlacementService) -
         * the booking/payment dual-write flag PlaceSingleReservation still
         * honors on the fast path.
         */
        public ?CustomerOrder $order,
        public array $orderLines,
        public array $bookings,
        public Payment $payment,
    ) {}
}

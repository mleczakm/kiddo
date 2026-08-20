<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Combined use case behind the customer-facing "cancel reservation and
 * request refund" action. Internally this is two separate domain
 * operations - asking for money back (RequestRefund) and releasing the seat
 * (CancelBookingOccurrence) - committed in a single transaction so a refund
 * that turns out to be ineligible (e.g. within 24h of the lesson) never
 * leaves the booking cancelled without a refund request behind it.
 *
 * RequestRefund runs first deliberately: for a single-lesson booking,
 * cancelling the only lesson also flips the booking's own status to
 * "cancelled" (see CancelBookingOccurrence), and RequestRefund's own
 * eligibility checks require the booking to still be active/pending -
 * cancelling first would make every refund request on a single-lesson
 * booking fail with a misleading "not eligible" error.
 */
final readonly class CancelAndRequestRefund
{
    public function __construct(
        private CancelBookingOccurrence $cancelBookingOccurrence,
        private RequestRefund $requestRefund,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(Ulid $bookingId, Ulid $lessonId, int $actingUserId, ?string $reason): void
    {
        ($this->requestRefund)($bookingId, $lessonId, $actingUserId, $reason);
        ($this->cancelBookingOccurrence)($bookingId, $lessonId, $actingUserId, $reason);

        $this->em->flush();
    }
}

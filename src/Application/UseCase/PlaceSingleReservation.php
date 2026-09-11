<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\AddBooking;
use App\Application\Command\SendReservationNotification;
use App\Application\Consent\BookingConsentManager;
use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Repository\WaitlistEntryRepositoryInterface;
use App\Application\Service\Commerce\OrderItemSelection;
use App\Application\Service\Commerce\OrderPlacementOptions;
use App\Application\Service\Commerce\OrderPlacementService;
use App\Application\Service\NewBookingNotifier;
use App\Application\Service\Pricing\PriceQuoter;
use App\Application\Service\Pricing\ShadowPricingEvaluator;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Entity\TicketOption;
use Brick\Money\Money;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Uid\Ulid;

/**
 * Canonical use case for the fast (single ticket, single booking) reservation
 * path. AddBookingHandler is a thin Messenger adapter over this class.
 */
final readonly class PlaceSingleReservation
{
    public function __construct(
        private MessageBusInterface $bus,
        private UserRepositoryInterface $userRepository,
        private LessonRepositoryInterface $lessonRepository,
        private ChildRepositoryInterface $childRepository,
        private NewBookingNotifier $newBookingNotifier,
        private FeatureManager $featureManager,
        private OrderPlacementService $orderPlacementService,
        private ShadowPricingEvaluator $shadowPricing,
        private PriceQuoter $priceQuoter,
        private WaitlistEntryRepositoryInterface $waitlist,
        private BookingConsentManager $bookingConsentManager,
    ) {}

    public function __invoke(AddBooking $command): void
    {
        $user = $this->userRepository->find($command->userId);
        if ($user === null) {
            throw new \InvalidArgumentException(sprintf('User %d not found', $command->userId));
        }

        $lesson = $this->lessonRepository->find(Ulid::fromString($command->lessonId));
        if ($lesson === null) {
            throw new \InvalidArgumentException(sprintf('Lesson %s not found', $command->lessonId));
        }

        $ticketOption = $lesson->getMatchingTicketOption($command->ticketType);

        $quote = $this->priceQuoter->quote($user->getId(), $lesson, $ticketOption->type->value, $ticketOption->price);

        $appliedQuote = null;
        if ($this->featureManager->isEnabled('dynamic_pricing')) {
            // $command->expectedQuoteHash is null for callers that don't participate in the
            // quote/reconfirm flow (e.g. the chat booking tool) - they just get the current price.
            if ($command->expectedQuoteHash !== null && $command->expectedQuoteHash !== $quote->quoteHash) {
                throw new PriceQuoteMismatchException($quote);
            }

            $appliedQuote = $quote;
            $ticketOption = new TicketOption(
                $ticketOption->type,
                Money::ofMinor($quote->finalPriceMinor, $quote->currency),
                $ticketOption->description,
                $ticketOption->reschedulePolicy,
            );
        } else {
            $this->shadowPricing->evaluate($quote, $ticketOption->price->getMinorAmount()->toInt());
        }

        $child = null;
        if ($command->childId !== null) {
            $candidate = $this->childRepository->find(Ulid::fromString($command->childId));
            if ($candidate !== null && $candidate->getOwner()->getId() === $user->getId()) {
                $child = $candidate;
            }
        }

        $result = $this->orderPlacementService->place(
            user: $user,
            source: CustomerOrder::SOURCE_FAST_TRACK,
            paymentCode: $command->paymentCode,
            items: [new OrderItemSelection($lesson, $ticketOption, $child, $appliedQuote)],
            options: $this->featureManager->isEnabled('commerce_order_write')
                ? OrderPlacementOptions::standard()
                : OrderPlacementOptions::withoutOrder(),
        );
        $booking = $result->bookings[0];

        if ($command->legalAcceptanceConfirmed && $command->withdrawalAcknowledged) {
            $this->bookingConsentManager->recordAfterBooking($user, $booking, $lesson);
        }

        // If this parent was on the lesson's waitlist (waiting or holding an
        // offer), close that entry — they took the seat.
        $this->waitlist->findActiveForUserAndLesson($user, $lesson)?->claim(Clock::get()->now());

        $this->newBookingNotifier->notify($booking);

        // After commit: mailer failures must not roll back the booking/payment code.
        $this->bus->dispatch(new Envelope(
            new SendReservationNotification(
                $user->getEmail(),
                $user->getName(),
                $command->paymentCode,
                $result->payment->getAmount(),
                lessonTitle: $lesson->getMetadata()->title,
                lessonSchedule: $lesson->schedule,
                ticketType: $ticketOption->type->value,
                childName: $booking->getChild()?->getName(),
            ),
        )->with(new DispatchAfterCurrentBusStamp()));
    }
}

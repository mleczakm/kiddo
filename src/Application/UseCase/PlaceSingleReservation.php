<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\AddBooking;
use App\Application\Command\SendReservationNotification;
use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\BookingFactory;
use App\Application\Service\Commerce\FastTrackOrderFactory;
use App\Application\Service\InAppNotificationService;
use App\Application\Service\LessonInstructorResolver;
use App\Application\Service\Pricing\PriceQuoter;
use App\Application\Service\Pricing\ShadowPricingEvaluator;
use App\Entity\NotificationSeverity;
use App\Entity\PaymentCode;
use App\Entity\TicketOption;
use Brick\Money\Currency;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Canonical use case for the fast (single ticket, single booking) reservation
 * path. AddBookingHandler is a thin Messenger adapter over this class.
 */
final readonly class PlaceSingleReservation
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private UserRepositoryInterface $userRepository,
        private LessonRepositoryInterface $lessonRepository,
        private ChildRepositoryInterface $childRepository,
        private BookingFactory $bookingFactory,
        private InAppNotificationService $inAppNotifications,
        private LessonInstructorResolver $instructorResolver,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private FeatureManager $featureManager,
        private FastTrackOrderFactory $orderFactory,
        private ShadowPricingEvaluator $shadowPricing,
        private PriceQuoter $priceQuoter,
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

        $booking = $this->bookingFactory->createFrom($lesson, $ticketOption, $user);

        if ($command->childId !== null) {
            $child = $this->childRepository->find(Ulid::fromString($command->childId));
            if ($child !== null && $child->getOwner()->getId() === $user->getId()) {
                $booking->setChild($child);
            }
        }

        $payment = $booking->getPayment();
        if ($payment !== null && $payment->getPaymentCode() === null) {
            new PaymentCode($payment, $command->paymentCode);
        }

        if ($payment !== null && $this->featureManager->isEnabled('commerce_order_write')) {
            // Persisted (and thus scheduled for insert) before $booking below, whose cascade
            // will schedule $payment for insert: Doctrine has no mapped association between
            // Payment/Booking and CustomerOrder/OrderLine (deliberately, to stay decoupled -
            // see FastTrackOrderFactory), so it cannot infer the FK insert order on its own.
            [$order, $orderLine] = $this->orderFactory->create(
                $booking,
                $payment,
                $lesson,
                $ticketOption,
                $user,
                $appliedQuote,
            );
            $booking->setOrderLineId($orderLine->getId());
            $payment->setOrderId($order->getId());
            $this->em->persist($order);
            $this->em->persist($orderLine);
        }

        $this->em->persist($booking);

        foreach ($this->instructorResolver->resolve([$lesson], exclude: $user) as $instructor) {
            $this->inAppNotifications->notify(
                $instructor,
                $this->translator->trans('notifications.in_app.new_booking.instructor.title', [], 'messages'),
                $this->translator->trans(
                    'notifications.in_app.new_booking.instructor.body',
                    [
                        'name' => $user->getName(),
                        'lesson' => $lesson->getMetadata()->title,
                        'date' => $lesson->schedule->format('Y-m-d H:i'),
                    ],
                    'messages',
                ),
                $this->urlGenerator->generate('app_admin_lesson_view', [
                    'id' => (string) $lesson->getId(),
                ]),
                NotificationSeverity::Info,
            );
        }

        // After commit: mailer failures must not roll back the booking/payment code.
        $this->bus->dispatch(new Envelope(
            new SendReservationNotification(
                $user->getEmail(),
                $user->getName(),
                $command->paymentCode,
                $payment?->getAmount() ?? Money::zero(Currency::of('PLN')),
                lessonTitle: $lesson->getMetadata()->title,
                lessonSchedule: $lesson->schedule,
                ticketType: $ticketOption->type->value,
                childName: $booking->getChild()?->getName(),
            ),
        )->with(new DispatchAfterCurrentBusStamp()));
    }
}

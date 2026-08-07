<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\AddBooking;
use App\Application\Command\SendReservationNotification;
use App\Application\Service\BookingFactory;
use App\Application\Service\InAppNotificationService;
use App\Application\Service\LessonInstructorResolver;
use App\Entity\NotificationSeverity;
use App\Entity\PaymentCode;
use App\Repository\ChildRepository;
use App\Repository\LessonRepository;
use App\Repository\UserRepository;
use Brick\Money\Currency;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AddBookingHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
        private UserRepository $userRepository,
        private LessonRepository $lessonRepository,
        private ChildRepository $childRepository,
        private BookingFactory $bookingFactory,
        private InAppNotificationService $inAppNotifications,
        private LessonInstructorResolver $instructorResolver,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
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

        $this->em->persist($booking);

        foreach ($this->instructorResolver->resolve([$lesson], exclude: $user) as $instructor) {
            $this->inAppNotifications->notify(
                $instructor,
                $this->translator->trans('notifications.in_app.new_booking.instructor.title', [], 'messages'),
                $this->translator->trans('notifications.in_app.new_booking.instructor.body', [
                    'name' => $user->getName(),
                    'lesson' => $lesson->getMetadata()->title,
                    'date' => $lesson->getMetadata()->schedule->format('Y-m-d H:i'),
                ], 'messages'),
                $this->urlGenerator->generate('app_admin_lesson_view', ['id' => (string) $lesson->getId()]),
                NotificationSeverity::Info,
            );
        }

        // After commit: mailer failures must not roll back the booking/payment code.
        $this->bus->dispatch(
            new Envelope(new SendReservationNotification(
                $user->getEmail(),
                $user->getName(),
                $command->paymentCode,
                $payment?->getAmount() ?? Money::zero(Currency::of('PLN')),
                lessonTitle: $lesson->getMetadata()
                    ->title,
                lessonSchedule: $lesson->getMetadata()
                    ->schedule,
                ticketType: $ticketOption->type->value,
                childName: $booking->getChild()?->getName(),
            ))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}

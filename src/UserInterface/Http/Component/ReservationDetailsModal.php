<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Application\UseCase\ApproveRefund;
use App\Application\UseCase\DeclineRefund;
use App\Entity\ActivityLog;
use App\Entity\Booking;
use App\Entity\DTO\RescheduledLesson;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\ActivityLogRepository;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\Message\CancelLessonBooking;
use App\Message\ReactivateBooking;
use App\Message\RefundLessonBooking;
use App\Message\RescheduleLessonBooking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class ReservationDetailsModal extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp(writable: true)]
    public ?string $bookingId = null;

    #[LiveProp(writable: true)]
    public ?string $lessonId = null;

    #[LiveProp(writable: true)]
    public string $note = '';

    #[LiveProp(writable: true)]
    public string $action = '';

    #[LiveProp(writable: true)]
    public string $reason = '';

    #[LiveProp(writable: true)]
    public ?string $targetLessonId = null;

    #[LiveProp(writable: true)]
    public ?string $successMessage = null;

    #[LiveProp(writable: true)]
    public ?string $errorMessage = null;

    #[LiveProp(writable: true)]
    public string $paymentNote = '';

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly ActivityLogRepository $activityLogRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly ApproveRefund $approveRefund,
        private readonly DeclineRefund $declineRefund,
        private readonly RefundRequestRepositoryInterface $refundRequestRepository,
        #[Autowire(service: 'state_machine.payment')]
        private readonly WorkflowInterface $paymentStateMachine,
    ) {}

    #[LiveAction]
    #[LiveListener('openReservationDetails')]
    public function open(#[LiveArg] string $bookingId, #[LiveArg] ?string $lessonId = null): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_BOOKINGS');
        $booking = $this->findBooking($bookingId);
        if (!$booking instanceof Booking) {
            return;
        }

        $this->bookingId = $bookingId;
        $this->lessonId = $lessonId ?? $this->firstRelevantLessonId($booking);
        $this->note = $booking->getNotes() ?? '';
        $this->paymentNote = '';
        $this->modalOpened = true;
        $this->resetAction();
    }

    #[LiveAction]
    public function close(): void
    {
        $this->modalOpened = false;
        $this->resetAction();
    }

    #[LiveAction]
    public function selectLesson(#[LiveArg] string $lessonId): void
    {
        $this->lessonId = $lessonId;
        $this->resetAction();
    }

    #[LiveAction]
    public function reactivateBooking(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_BOOKINGS');
        $booking = $this->getBooking();
        $actor = $this->getUser();
        if (!$booking instanceof Booking || !$actor instanceof User) {
            $this->errorMessage = 'Nie udało się odnaleźć rezerwacji.';
            return;
        }

        try {
            $this->messageBus->dispatch(
                new ReactivateBooking($booking->getId(), $actor, 'Przywrócono przez administratora'),
            );
            $this->successMessage = 'Rezerwacja została przywrócona.';
            $this->errorMessage = null;
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
            $this->successMessage = null;
        }
    }

    public function canReactivateBooking(): bool
    {
        return $this->getBooking()?->canBeReactivated() ?? false;
    }

    #[LiveAction]
    public function selectAction(#[LiveArg] string $value): void
    {
        $action = $value;
        if (!in_array($action, ['cancel', 'refund', 'reschedule'], true)) {
            $action = '';
        }

        $this->action = $action;
        $this->reason = '';
        $this->targetLessonId = null;
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    #[LiveAction]
    public function saveNote(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_BOOKINGS');
        $booking = $this->getBooking();
        if (!$booking instanceof Booking) {
            return;
        }

        $note = trim($this->note);
        $booking->setNotes($note === '' ? null : $note);
        $this->entityManager->flush();
        $this->successMessage = 'Notatka została zapisana.';
        $this->errorMessage = null;
    }

    #[LiveAction]
    public function changePaymentStatus(#[LiveArg] string $transition): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_BOOKINGS');
        $payment = $this->getBooking()?->getPayment();
        $actor = $this->getUser();
        if (!$payment instanceof Payment || !$actor instanceof User) {
            $this->errorMessage = 'Nie udało się odnaleźć płatności.';
            return;
        }

        $actorId = $actor->getId();
        $allowedTransitions = [
            Payment::TRANSITION_REFUND,
            Payment::TRANSITION_DECLINE_REFUND,
            Payment::TRANSITION_CANCEL,
        ];
        if (
            !in_array($transition, $allowedTransitions, true)
            || !$this->paymentStateMachine->can($payment, $transition)
            || $actorId === null
        ) {
            $this->errorMessage = 'Ta zmiana statusu płatności nie jest dostępna.';
            return;
        }

        try {
            if ($transition === Payment::TRANSITION_CANCEL) {
                $this->paymentStateMachine->apply($payment, $transition);
                $payment->recordStatusDecision($actor, $this->paymentNote);
                $this->entityManager->flush();

                $this->successMessage = 'Płatność została oznaczona jako anulowana.';
                $this->errorMessage = null;
                $this->paymentNote = '';
                return;
            }

            $refundRequest = $this->refundRequestRepository->findPendingForPayment($payment);
            if ($refundRequest === null) {
                throw new \RuntimeException('Nie znaleziono oczekującego wniosku o zwrot dla tej płatności.');
            }

            if ($transition === Payment::TRANSITION_REFUND) {
                ($this->approveRefund)($refundRequest->getId(), $actorId, $this->paymentNote);
            } else {
                ($this->declineRefund)($refundRequest->getId(), $actorId, $this->paymentNote);
            }
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
            $this->successMessage = null;
            return;
        }

        $this->successMessage = $transition === Payment::TRANSITION_REFUND
            ? 'Płatność została oznaczona jako zwrócona.'
            : 'Wniosek o zwrot został odrzucony.';
        $this->errorMessage = null;
        $this->paymentNote = '';
    }

    public function canChangePaymentStatus(string $transition): bool
    {
        $payment = $this->getBooking()?->getPayment();

        return $payment instanceof Payment && $this->paymentStateMachine->can($payment, $transition);
    }

    #[LiveAction]
    public function executeAction(): void
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGE_BOOKINGS');
        $booking = $this->getBooking();
        $lesson = $this->getLesson();
        $actor = $this->getUser();
        if (!$booking instanceof Booking || !$lesson instanceof Lesson || !$actor instanceof User) {
            $this->errorMessage = 'Nie udało się odnaleźć rezerwacji lub zajęć.';
            return;
        }

        try {
            match ($this->action) {
                'cancel' => $this->messageBus->dispatch(
                    new CancelLessonBooking($booking->getId(), $lesson->getId(), $actor, trim($this->reason)),
                ),
                'refund' => $this->messageBus->dispatch(
                    new RefundLessonBooking($booking->getId(), $lesson->getId(), $actor, trim($this->reason)),
                ),
                'reschedule' => $this->reschedule($booking, $lesson, $actor),
                default => throw new \InvalidArgumentException('Wybierz akcję.'),
            };
        } catch (\Throwable $exception) {
            $this->errorMessage = $exception->getMessage();
            $this->successMessage = null;
            return;
        }

        $this->successMessage = match ($this->action) {
            'cancel' => 'Rezerwacja na wybrane zajęcia została anulowana.',
            'refund' => 'Anulowanie ze zwrotem zostało zlecone.',
            default => 'Rezerwacja została przełożona.',
        };
        $this->errorMessage = null;
        $this->resetAction(keepMessages: true);
    }

    public function getBooking(): ?Booking
    {
        return $this->bookingId === null ? null : $this->findBooking($this->bookingId);
    }

    public function getLesson(): ?Lesson
    {
        if ($this->lessonId === null) {
            return null;
        }

        try {
            return $this->lessonRepository->find(Ulid::fromString($this->lessonId));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<Lesson>
     */
    public function getAvailableLessons(): array
    {
        $booking = $this->getBooking();
        $lesson = $this->getLesson();
        $series = $lesson?->getSeries();
        if (!$booking instanceof Booking || !$lesson instanceof Lesson || $series === null) {
            return [];
        }

        return array_values(array_filter(
            $this->lessonRepository->findAvailableLessonsForReschedule($series, $lesson->schedule),
            static fn(Lesson $candidate): bool => (
                !$candidate->getId()->equals($lesson->getId())
                && !$booking->getLessons()->contains($candidate)
                && $candidate->getAvailableSpots() > 0
            ),
        ));
    }

    /**
     * @return list<array{from: ?Lesson, to: ?Lesson, at: ?\DateTimeImmutable}>
     */
    public function getRescheduleHistory(): array
    {
        $booking = $this->getBooking();
        if (!$booking instanceof Booking) {
            return [];
        }

        $history = [];
        foreach ($booking->getLessonsMap()->getRescheduled() as $entry) {
            if (!$entry instanceof RescheduledLesson) {
                continue;
            }
            $history[] = [
                'from' => $this->lessonRepository->find($entry->rescheduledFrom),
                'to' => $this->lessonRepository->find($entry->lessonId),
                'at' => $entry->rescheduledAt,
            ];
        }

        return $history;
    }

    /**
     * @return list<array{type: string, title: string, summary: ?string, createdAt: \DateTimeImmutable}>
     */
    public function getBookingEvents(): array
    {
        if ($this->bookingId === null) {
            return [];
        }

        return array_values(array_map(static fn(ActivityLog $log): array => [
            'type' => $log->getType()->value,
            'title' => $log->getTitle(),
            'summary' => $log->getSummary(),
            'createdAt' => $log->getCreatedAt(),
        ], $this->activityLogRepository->findByBookingId($this->bookingId)));
    }

    private function reschedule(Booking $booking, Lesson $lesson, User $actor): Envelope
    {
        if ($this->targetLessonId === null) {
            throw new \InvalidArgumentException('Wybierz nowe zajęcia z serii.');
        }
        $target = $this->lessonRepository->find(Ulid::fromString($this->targetLessonId));
        if (!$target instanceof Lesson) {
            throw new \InvalidArgumentException('Wybrane zajęcia nie istnieją.');
        }

        $envelope = $this->messageBus->dispatch(
            new RescheduleLessonBooking(
                $booking->getId(),
                $lesson->getId(),
                $target->getId(),
                $actor,
                trim($this->reason),
            ),
        );
        $this->lessonId = $target->getId()->toString();

        return $envelope;
    }

    private function findBooking(string $bookingId): ?Booking
    {
        try {
            return $this->bookingRepository->find(Ulid::fromString($bookingId));
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstRelevantLessonId(Booking $booking): ?string
    {
        $modifiableLessons = $booking->getModifiableLessons();
        $firstKey = array_key_first($modifiableLessons);
        if ($firstKey !== null) {
            return $modifiableLessons[$firstKey]->lessonId->toString();
        }

        $lesson = $booking->getLessons()->first();

        return $lesson instanceof Lesson ? $lesson->getId()->toString() : null;
    }

    private function resetAction(bool $keepMessages = false): void
    {
        $this->action = '';
        $this->reason = '';
        $this->targetLessonId = null;
        if (!$keepMessages) {
            $this->successMessage = null;
            $this->errorMessage = null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\DTO\RescheduledLesson;
use App\Entity\Lesson;
use App\Entity\User;
use App\Message\CancelLessonBooking;
use App\Message\RefundLessonBooking;
use App\Message\RescheduleLessonBooking;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Uid\Ulid;
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

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly LessonRepository $lessonRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[LiveAction]
    #[LiveListener('openReservationDetails')]
    public function open(#[LiveArg] string $bookingId, #[LiveArg] ?string $lessonId = null): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $booking = $this->findBooking($bookingId);
        if (! $booking instanceof Booking) {
            return;
        }

        $this->bookingId = $bookingId;
        $this->lessonId = $lessonId ?? $this->firstRelevantLessonId($booking);
        $this->note = $booking->getNotes() ?? '';
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
    public function selectAction(#[LiveArg] string $value): void
    {
        $action = $value;
        if (! in_array($action, ['cancel', 'refund', 'reschedule'], true)) {
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $booking = $this->getBooking();
        if (! $booking instanceof Booking) {
            return;
        }

        $booking->setNotes(trim($this->note) ?: null);
        $this->entityManager->flush();
        $this->successMessage = 'Notatka została zapisana.';
        $this->errorMessage = null;
    }

    #[LiveAction]
    public function executeAction(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $booking = $this->getBooking();
        $lesson = $this->getLesson();
        $actor = $this->getUser();
        if (! $booking instanceof Booking || ! $lesson instanceof Lesson || ! $actor instanceof User) {
            $this->errorMessage = 'Nie udało się odnaleźć rezerwacji lub zajęć.';
            return;
        }

        try {
            match ($this->action) {
                'cancel' => $this->messageBus->dispatch(new CancelLessonBooking(
                    $booking->getId(),
                    $lesson->getId(),
                    $actor,
                    trim($this->reason),
                )),
                'refund' => $this->messageBus->dispatch(new RefundLessonBooking(
                    $booking->getId(),
                    $lesson->getId(),
                    $actor,
                    trim($this->reason),
                )),
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
        if (! $booking instanceof Booking || ! $lesson instanceof Lesson || $series === null) {
            return [];
        }

        return array_values(array_filter(
            $this->lessonRepository->findAvailableLessonsForReschedule($series, $lesson->getMetadata()->schedule),
            static fn(Lesson $candidate): bool => ! $candidate->getId()
                ->equals($lesson->getId())
                && ! $booking->getLessons()
                    ->contains($candidate)
                && $candidate->getAvailableSpots() > 0,
        ));
    }

    /**
     * @return list<array{from: ?Lesson, to: ?Lesson, at: ?\DateTimeImmutable}>
     */
    public function getRescheduleHistory(): array
    {
        $booking = $this->getBooking();
        if (! $booking instanceof Booking) {
            return [];
        }

        $history = [];
        foreach ($booking->getLessonsMap()->getRescheduled() as $entry) {
            if (! $entry instanceof RescheduledLesson) {
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

    private function reschedule(Booking $booking, Lesson $lesson, User $actor): Envelope
    {
        if ($this->targetLessonId === null) {
            throw new \InvalidArgumentException('Wybierz nowe zajęcia z serii.');
        }
        $target = $this->lessonRepository->find(Ulid::fromString($this->targetLessonId));
        if (! $target instanceof Lesson) {
            throw new \InvalidArgumentException('Wybrane zajęcia nie istnieją.');
        }

        $envelope = $this->messageBus->dispatch(new RescheduleLessonBooking(
            $booking->getId(),
            $lesson->getId(),
            $target->getId(),
            $actor,
            trim($this->reason),
        ));
        $this->lessonId = $target->getId()
            ->toString();

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
        foreach ($booking->getModifiableLessons() as $bookedLesson) {
            return $bookedLesson->lessonId->toString();
        }
        $lesson = $booking->getLessons()
            ->first();

        return $lesson instanceof Lesson ? $lesson->getId()
            ->toString() : null;
    }

    private function resetAction(bool $keepMessages = false): void
    {
        $this->action = '';
        $this->reason = '';
        $this->targetLessonId = null;
        if (! $keepMessages) {
            $this->successMessage = null;
            $this->errorMessage = null;
        }
    }
}

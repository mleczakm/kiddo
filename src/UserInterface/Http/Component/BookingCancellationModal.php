<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Message\CancelLessonBooking;
use App\Message\RefundLessonBooking;
use App\Message\RescheduleLessonBooking;
use App\Repository\LessonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class BookingCancellationModal extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LessonRepository $lessonRepository
    ) {}

    #[LiveProp]
    public ?Booking $booking = null;

    #[LiveProp]
    public ?Lesson $lesson = null;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp(writable: true)]
    public string $cancellationReason = '';

    #[LiveProp(writable: true)]
    public string $selectedOption = 'reschedule';

    #[LiveProp(writable: true)]
    public ?Ulid $selectedLessonId = null;

    private const CANCELLATION_TYPES = ['reschedule', 'refund', 'cancel'];

    /**
     * @return array<int, Lesson>
     */
    public function getAvailableLessons(): array
    {
        if (! $this->booking || ! $this->lesson) {
            return [];
        }

        $series = $this->lesson->getSeries();
        if (! $series) {
            return [];
        }

        // Get all future lessons in the same series that have available spots
        $availableLessons = $this->lessonRepository->findAvailableLessonsForReschedule(
            $series,
            $this->lesson->getMetadata()
                ->schedule,
        );

        // Remove the current lesson from the list
        return array_filter($availableLessons, fn($lesson) => $lesson->getId() !== $this->lesson->getId());
    }

    public function getTabState(string $option): string
    {
        return $this->selectedOption === $option ? 'active' : 'inactive';
    }

    public function isTabActive(string $option): bool
    {
        return $this->selectedOption === $option;
    }

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
    }

    #[LiveAction]
    public function processCancellation(
        string $typeParam,
        ?int $lessonIdParam = null,
        ?string $reasonParam = null
    ): void {
        if (! in_array($typeParam, self::CANCELLATION_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid cancellation type');
        }

        if (! $this->booking || ! $this->lesson) {
            throw new \RuntimeException('Booking or lesson not set');
        }

        $this->cancellationReason = $reasonParam ?? $this->cancellationReason;

        switch ($typeParam) {
            case 'reschedule':
                $newLesson = $this->lessonRepository->find($lessonIdParam);
                if (! $newLesson) {
                    throw new \RuntimeException('Selected lesson not found');
                }

                $user = $this->getUser();
                if (! $user) {
                    throw new \RuntimeException('User not authenticated');
                }

                $this->messageBus->dispatch(new RescheduleLessonBooking(
                    $this->booking->getId(),
                    $this->lesson->getId(),
                    $newLesson->getId(),
                    Ulid::fromString($user->getUserIdentifier()),
                    $this->cancellationReason
                ));
                break;

            case 'refund':
                $user = $this->getUser();
                if (! $user) {
                    throw new \RuntimeException('User not authenticated');
                }

                $this->messageBus->dispatch(new RefundLessonBooking(
                    $this->booking->getId(),
                    $this->lesson->getId(),
                    Ulid::fromString($user->getUserIdentifier()),
                    $this->cancellationReason
                ));
                break;

            case 'cancel':
            default:
                $user = $this->getUser();
                if (! $user) {
                    throw new \RuntimeException('User not authenticated');
                }

                $this->messageBus->dispatch(new CancelLessonBooking(
                    $this->booking->getId(),
                    $this->lesson->getId(),
                    Ulid::fromString($user->getUserIdentifier()),
                    $this->cancellationReason
                ));
                break;
        }

        // Reset the form
        $this->modalOpened = false;
        $this->selectedOption = 'reschedule';
        $this->cancellationReason = '';
        $this->selectedLessonId = null;
    }

    #[LiveAction]
    public function selectTab(#[LiveArg] int $index, #[LiveArg('option')] string $option): void
    {
        $this->selectedOption = $option;
    }

    public function canBeRescheduled(): bool
    {
        if (! $this->booking || ! $this->lesson) {
            return false;
        }

        $series = $this->lesson->getSeries();
        $schedule = $this->lesson->getMetadata()
            ->schedule;

        return $series !== null &&
            $schedule > new \DateTimeImmutable() &&
            count($this->getAvailableLessons()) > 0;
    }

    public function isButtonDisabled(): bool
    {
        return true;
    }
}

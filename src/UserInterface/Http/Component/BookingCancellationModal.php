<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use App\Message\CancelLessonBooking;
use App\Message\RefundLessonBooking;
use App\Message\RescheduleLessonBooking;
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
        private readonly LessonRepository $lessonRepository,
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

    #[LiveProp(writable: true)]
    public bool $lateCancelAcknowledged = false;

    private const array CANCELLATION_TYPES = ['reschedule', 'refund', 'cancel'];

    /**
     * @return array<int, Lesson>
     */
    public function getAvailableLessons(): array
    {
        $booking = $this->booking;
        $currentLesson = $this->lesson;
        if ($booking === null || $currentLesson === null) {
            return [];
        }

        $series = $currentLesson->getSeries();
        if (!$series) {
            return [];
        }

        $availableLessons = $this->lessonRepository->findAvailableLessonsForReschedule(
            $series,
            $currentLesson->schedule,
        );

        return array_values(array_filter(
            $availableLessons,
            static fn(Lesson $lesson): bool => (
                !$lesson->getId()->equals($currentLesson->getId())
                && !$booking->getLessons()->contains($lesson)
                && $lesson->getAvailableSpots() > 0
            ),
        ));
    }

    public function getTabState(string $option): string
    {
        return $this->selectedOption === $option ? 'active' : 'inactive';
    }

    public function isTabActive(string $option): bool
    {
        return $this->selectedOption === $option;
    }

    public function isLateCancellation(): bool
    {
        return $this->lesson !== null && !$this->lesson->cancellationAvailable();
    }

    public function requiresLateCancelAcknowledgment(): bool
    {
        return (
            $this->booking !== null
            && $this->lesson !== null
            && $this->booking->requiresNoRefundCancelWarning($this->lesson)
        );
    }

    /** @throws \LogicException */
    public function canShowRescheduleTab(): bool
    {
        return $this->canBeRescheduled();
    }

    public function canShowRefundTab(): bool
    {
        $booking = $this->booking;
        $currentLesson = $this->lesson;
        if ($booking === null || $currentLesson === null) {
            return false;
        }

        if (!$booking->hasPaidPayment()) {
            return false;
        }

        return $booking->canRequestRefundForLesson($currentLesson) || $this->isAdmin();
    }

    /** @throws \Symfony\Component\Uid\Exception\InvalidArgumentException */
    public function canShowCancelTab(): bool
    {
        $booking = $this->booking;
        $currentLesson = $this->lesson;
        if ($booking === null || $currentLesson === null) {
            return false;
        }

        return $booking->canCancelLesson($currentLesson) || $this->isAdmin();
    }

    /** @throws \LogicException */
    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;
        $this->ensureValidSelectedOption();
    }

    /** @throws \LogicException */
    #[LiveAction]
    public function openWithOption(#[LiveArg('option')] string $option): void
    {
        if (in_array($option, self::CANCELLATION_TYPES, true)) {
            $this->selectedOption = $option;
        }
        $this->modalOpened = true;
        $this->ensureValidSelectedOption();
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \RuntimeException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[LiveAction]
    public function processCancellation(#[LiveArg('type')] string $typeParam): void
    {
        if (!in_array($typeParam, self::CANCELLATION_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid cancellation type: ' . $typeParam);
        }

        $booking = $this->booking;
        $lesson = $this->lesson;
        if ($booking === null || $lesson === null) {
            throw new \RuntimeException('Booking or lesson not set');
        }

        $securityUser = $this->getUser();
        if (!$securityUser instanceof User) {
            throw new \RuntimeException('User not authenticated or invalid user type');
        }

        if (
            $typeParam === 'cancel'
            && $this->requiresLateCancelAcknowledgment()
            && !$this->lateCancelAcknowledged
            && !$this->isAdmin()
        ) {
            throw new \RuntimeException('Late cancellation must be acknowledged');
        }

        if ($typeParam === 'reschedule' && !$booking->canRescheduleLesson($lesson) && !$this->isAdmin()) {
            throw new \RuntimeException('Reschedule is not allowed for this booking');
        }

        if ($typeParam === 'refund' && !$booking->canRequestRefundForLesson($lesson) && !$this->isAdmin()) {
            throw new \RuntimeException('Refund is not available within 24h of the lesson');
        }

        switch ($typeParam) {
            case 'reschedule':
                $newLesson = $this->lessonRepository->find($this->selectedLessonId);
                if (!$newLesson) {
                    throw new \RuntimeException('Selected lesson not found');
                }

                $this->messageBus->dispatch(
                    new RescheduleLessonBooking(
                        $booking->getId(),
                        $lesson->getId(),
                        $newLesson->getId(),
                        $securityUser,
                        $this->cancellationReason,
                    ),
                );
                break;

            case 'refund':
                $this->messageBus->dispatch(
                    new RefundLessonBooking(
                        $booking->getId(),
                        $lesson->getId(),
                        $securityUser,
                        $this->cancellationReason,
                    ),
                );
                break;

            case 'cancel':
            default:
                $this->messageBus->dispatch(
                    new CancelLessonBooking(
                        $booking->getId(),
                        $lesson->getId(),
                        $securityUser,
                        $this->cancellationReason,
                    ),
                );
                break;
        }

        $this->modalOpened = false;
        $this->selectedOption = 'reschedule';
        $this->cancellationReason = '';
        $this->selectedLessonId = null;
        $this->lateCancelAcknowledged = false;
    }

    #[LiveAction]
    public function selectTab(#[LiveArg('option')] string $option): void
    {
        $this->selectedOption = $option;
    }

    /** @throws \LogicException */
    public function canBeRescheduled(): bool
    {
        $booking = $this->booking;
        $lesson = $this->lesson;
        if ($booking === null || $lesson === null) {
            return false;
        }

        if ($this->isAdmin()) {
            $series = $lesson->getSeries();

            return $series !== null && $lesson->future() && count($this->getAvailableLessons()) > 0;
        }

        return $booking->canRescheduleLesson($lesson) && count($this->getAvailableLessons()) > 0;
    }

    public function isButtonDisabled(): bool
    {
        if ($this->selectedOption === 'reschedule') {
            return $this->selectedLessonId === null;
        }

        if ($this->selectedOption === 'cancel' && $this->requiresLateCancelAcknowledgment() && !$this->isAdmin()) {
            return !$this->lateCancelAcknowledged;
        }

        return false;
    }

    /** @throws \LogicException */
    private function ensureValidSelectedOption(): void
    {
        if ($this->selectedOption === 'reschedule' && !$this->canShowRescheduleTab()) {
            $this->selectedOption = $this->canShowRefundTab() ? 'refund' : 'cancel';
        }

        if ($this->selectedOption === 'refund' && !$this->canShowRefundTab()) {
            $this->selectedOption = $this->canShowRescheduleTab() ? 'reschedule' : 'cancel';
        }

        if ($this->isLateCancellation() && !$this->canShowRescheduleTab() && !$this->canShowRefundTab()) {
            $this->selectedOption = 'cancel';
        }
    }

    /** @throws \LogicException */
    private function isAdmin(): bool
    {
        return $this->isGranted('ROLE_ADMIN');
    }
}

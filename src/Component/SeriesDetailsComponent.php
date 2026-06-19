<?php

declare(strict_types=1);

namespace App\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Series;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('SeriesDetails', template: 'components/SeriesDetailsComponent.html.twig')]
class SeriesDetailsComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public Ulid $seriesId;

    #[LiveProp]
    public bool $showPast = false;

    #[LiveProp]
    public ?Ulid $expandedLessonId = null;

    private ?Series $series = null;

    public function __construct(
        private readonly LessonRepository $lessonRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function mount(Ulid $seriesId): void
    {
        $this->seriesId = $seriesId;
        $this->loadSeries();
    }

    private function loadSeries(): void
    {
        $this->series = $this->entityManager->find(Series::class, $this->seriesId);
    }

    public function getSeries(): ?Series
    {
        if ($this->series === null) {
            $this->loadSeries();
        }

        return $this->series;
    }

    /**
     * @return Lesson[]
     */
    public function getLessons(): array
    {
        $series = $this->getSeries();
        if ($series === null) {
            return [];
        }

        $lessons = $series->lessons->toArray();

        // Sort by schedule
        usort($lessons, fn(Lesson $a, Lesson $b) => $a->getMetadata()->schedule <=> $b->getMetadata()->schedule);

        // Filter based on showPast
        if (! $this->showPast) {
            $now = Clock::get()->now();
            $lessons = array_filter($lessons, fn(Lesson $lesson) => $lesson->getMetadata()->schedule >= $now);
        }

        return array_values($lessons);
    }

    /**
     * @return Lesson[]
     */
    public function getPastLessons(): array
    {
        $series = $this->getSeries();
        if ($series === null) {
            return [];
        }

        $lessons = $series->lessons->toArray();

        // Sort by schedule
        usort($lessons, fn(Lesson $a, Lesson $b) => $b->getMetadata()->schedule <=> $a->getMetadata()->schedule);

        // Only past lessons
        $now = Clock::get()->now();
        $lessons = array_filter($lessons, fn(Lesson $lesson) => $lesson->getMetadata()->schedule < $now);

        return array_values($lessons);
    }

    public function hasPastLessons(): bool
    {
        return count($this->getPastLessons()) > 0;
    }

    /**
     * @return Booking[]
     */
    public function getBookingsForLesson(Lesson $lesson): array
    {
        return $this->bookingRepository->findBy([
            'lesson' => $lesson,
        ]);
    }

    /**
     * @return Booking[]
     */
    public function getActiveBookingsForLesson(Lesson $lesson): array
    {
        $bookings = $this->getBookingsForLesson($lesson);
        return array_filter(
            $bookings,
            fn(Booking $b)
            => $b->getStatus() !== Booking::STATUS_CANCELLED && $b->getStatus() !== Booking::STATUS_PAST
        );
    }

    /**
     * @return Booking[]
     */
    public function getWaitingApprovalBookingsForLesson(Lesson $lesson): array
    {
        $bookings = $this->getBookingsForLesson($lesson);
        return array_filter($bookings, fn(Booking $b) => $b->isWaitingApproval());
    }

    public function getPaidCountForLesson(Lesson $lesson): int
    {
        $bookings = $this->getBookingsForLesson($lesson);
        return count(array_filter($bookings, fn(Booking $b) => $b->isConfirmed()));
    }

    public function getStatusBadgeClass(string $status): string
    {
        return match ($status) {
            Booking::STATUS_ACTIVE => 'bg-green-100 text-green-700',
            Booking::STATUS_PENDING => 'bg-amber-100 text-amber-700',
            Booking::STATUS_WAITING_APPROVAL => 'bg-purple-100 text-purple-700',
            Booking::STATUS_CANCELLED => 'bg-red-100 text-red-700',
            Booking::STATUS_PAST => 'bg-slate-100 text-slate-500',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            Booking::STATUS_ACTIVE => 'Opłacone',
            Booking::STATUS_PENDING => 'Oczekuje na wpłatę',
            Booking::STATUS_WAITING_APPROVAL => 'Oczekuje na akceptację',
            Booking::STATUS_CANCELLED => 'Anulowane',
            Booking::STATUS_PAST => 'Zakończone',
            default => $status,
        };
    }

    #[LiveAction]
    public function toggleShowPast(): void
    {
        $this->showPast = ! $this->showPast;
    }

    #[LiveAction]
    public function expandLesson(Ulid $lessonId): void
    {
        $this->expandedLessonId = $this->expandedLessonId === $lessonId ? null : $lessonId;
    }

    #[LiveAction]
    public function approveBooking(string $bookingId): void
    {
        $booking = $this->entityManager->getRepository(Booking::class)->find($bookingId);
        if ($booking === null) {
            return;
        }

        $currentUser = $this->getUser();
        if ($currentUser === null) {
            return;
        }

        try {
            $booking->approve($currentUser);
            $this->entityManager->flush();
        } catch (\LogicException) {
            // Ignore if booking cannot be approved
        }
    }

    public function isLessonExpanded(Lesson $lesson): bool
    {
        return $this->expandedLessonId !== null && $this->expandedLessonId->equals($lesson->getId());
    }
}

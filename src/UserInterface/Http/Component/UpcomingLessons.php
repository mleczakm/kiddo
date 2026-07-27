<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\LessonRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('UpcomingLessons')]
class UpcomingLessons
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public ?string $query = null;

    #[LiveProp(writable: true, url: true)]
    public ?int $age = null;

    #[LiveProp(writable: true, url: true)]
    public string $week;

    #[LiveProp]
    public bool $showSearch = true;

    #[LiveProp]
    public ?int $limit = null;

    #[LiveProp]
    public ?string $openSlug = null;

    #[LiveProp]
    public ?string $openDate = null;

    #[LiveProp]
    public ?string $openHour = null;

    #[LiveProp(writable: true)]
    public string $view = 'grid';

    public function __construct(
        private LessonRepository $lessonRepository,
        private BookingRepository $bookingRepository,
        private Security $security,
    ) {
        $this->week = Clock::get()->now()->format('Y-m-d');
    }

    #[LiveAction]
    public function setView(#[LiveArg('view')] string $newView): void
    {
        $this->view = $newView;
        $this->openSlug = null;
        $this->openDate = null;
        $this->openHour = null;
    }

    /**
     * @return Lesson[]
     */
    public function getWorkshops(): array
    {
        return $this->lessonRepository->findByFilters($this->query, $this->age, $this->week, $this->limit);
    }

    /**
     * Get bookings for the current user across the displayed lessons
     * @return array<string, array<int, array{childName: string|null, paymentStatus: string, bookingId: string}>>
     */
    public function getUserBookingsByLesson(): array
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            return [];
        }

        $workshops = $this->getWorkshops();
        if ($workshops === []) {
            return [];
        }

        $lessonIdSet = [];
        foreach ($workshops as $workshop) {
            $lessonIdSet[(string) $workshop->getId()] = true;
        }

        $bookings = $this->bookingRepository->findForUserAndLessons($user, $workshops);

        $result = [];
        foreach ($bookings as $booking) {
            foreach ($booking->getLessons() as $lesson) {
                $lessonId = (string) $lesson->getId();
                if (! isset($lessonIdSet[$lessonId])) {
                    continue;
                }

                $paymentStatus = $booking->getPayment()?->getStatus() === Payment::STATUS_PAID
                    ? 'paid'
                    : 'unpaid';

                $result[$lessonId][] = [
                    'childName' => $booking->getChild()?->getName(),
                    'paymentStatus' => $paymentStatus,
                    'bookingId' => (string) $booking->getId(),
                ];
            }
        }

        return $result;
    }

    /**
     * Group workshops by day for calendar view
     * @return array<int, array{date: string, weekday: string, workshops: array<Lesson>}>
     */
    public function getWorkshopsByDay(): array
    {
        $workshops = $this->getWorkshops();
        $grouped = [];

        $weekStart = $this->getWeekStart();

        // Create 7 days from week start (Monday to Sunday)
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->modify("+{$i} days");
            $dateStr = $date->format('Y-m-d');
            $weekday = $date->format('l');

            $grouped[$dateStr] = [
                'date' => $dateStr,
                'weekday' => $weekday,
                'workshops' => [],
            ];
        }

        // Group workshops by their schedule date
        foreach ($workshops as $workshop) {
            $scheduleDate = $workshop->getMetadata()
                ->schedule->format('Y-m-d');
            if (isset($grouped[$scheduleDate])) {
                $grouped[$scheduleDate]['workshops'][] = $workshop;
            }
        }

        return array_values($grouped);
    }

    public function shouldOpenModal(Lesson $lesson): bool
    {
        if ($this->openSlug === null || $this->openSlug === '') {
            return false;
        }

        $metadata = $lesson->getMetadata();
        if ($metadata->slug !== $this->openSlug) {
            return false;
        }

        if ($this->openDate === null || $this->openHour === null) {
            return true;
        }

        return $metadata->schedule->format('Y-m-d') === $this->openDate
            && $metadata->schedule->format('H:i') === $this->openHour;
    }

    public function getCurrentWeek(): string
    {
        return Clock::get()->now()->format('Y-m-d');
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->week);
    }

    public function getWeekEnd(): \DateTimeImmutable
    {
        return $this->getWeekStart()
            ->modify('+7 days');
    }
}

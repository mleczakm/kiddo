<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Customer panel home. Composes existing reads into an at-a-glance view -
 * the thing ActiveNow's client panel is missing (it lands you on a raw
 * upcoming-classes list). Outstanding payments are a separate component
 * rendered above this one.
 */
#[AsLiveComponent]
final class PanelOverviewComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly NotificationRepository $notifications,
        private readonly Security $security,
    ) {}

    /**
     * The soonest upcoming, non-cancelled booked lesson.
     *
     * @return array{lesson: Lesson, booking: Booking}|null
     */
    public function getNextLesson(): ?array
    {
        return $this->upcomingBookedLessons()[0] ?? null;
    }

    /**
     * Active multi-lesson bookings (carnets) with a compact progress summary.
     *
     * @return list<array{title: string, remaining: int, total: int, next: ?\DateTimeImmutable}>
     */
    public function getCarnets(): array
    {
        return array_values(array_map(
            $this->summariseCarnet(...),
            array_filter($this->visibleBookings(), $this->isCarnet(...)),
        ));
    }

    public function getUnreadNotificationCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notifications->countUnreadForUser($user) : 0;
    }

    /**
     * Every future, non-cancelled booked lesson, soonest first.
     *
     * @return list<array{lesson: Lesson, booking: Booking}>
     */
    private function upcomingBookedLessons(): array
    {
        $rows = [];

        foreach ($this->visibleBookings() as $booking) {
            foreach ($this->futureActiveLessons($booking) as $lesson) {
                $rows[] = ['lesson' => $lesson, 'booking' => $booking];
            }
        }

        usort($rows, static fn(array $a, array $b): int => $a['lesson']->schedule <=> $b['lesson']->schedule);

        return $rows;
    }

    /**
     * @return list<Lesson>
     */
    private function futureActiveLessons(Booking $booking): array
    {
        $now = Clock::get()->now();
        $map = $booking->getLessonsMap();
        $lessons = [];

        foreach ($booking->getLessons() as $lesson) {
            if ($lesson->schedule <= $now || $map->isCancelledLesson($lesson->getId())) {
                continue;
            }
            $lessons[] = $lesson;
        }

        usort($lessons, static fn(Lesson $a, Lesson $b): int => $a->schedule <=> $b->schedule);

        return $lessons;
    }

    private function isCarnet(Booking $booking): bool
    {
        return $booking->isActive() && $booking->getLessonsMap()->getTotalCount() > 1;
    }

    /**
     * @return array{title: string, remaining: int, total: int, next: ?\DateTimeImmutable}
     */
    private function summariseCarnet(Booking $booking): array
    {
        $map = $booking->getLessonsMap();
        $upcoming = $this->futureActiveLessons($booking);

        return [
            'title' => $booking->getTitle(),
            'remaining' => $map->getActiveCount(),
            'total' => $map->getActiveCount() + $map->getPastCount(),
            'next' => $upcoming === [] ? null : $upcoming[0]->schedule,
        ];
    }

    /**
     * @return list<Booking>
     */
    private function visibleBookings(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->bookingRepository->findVisibleForUser($user) : [];
    }
}

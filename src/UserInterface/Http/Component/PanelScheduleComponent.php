<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Agenda of the user's upcoming booked lessons, grouped by day - the panel's
 * "Nadchodzące zajęcia". Unlike ActiveNow's next-7-days-only view it offers a
 * range selector and a "today" emphasis, and each row keeps the existing
 * reschedule / cancel affordances (BookingCancellationModal).
 */
#[AsLiveComponent]
final class PanelScheduleComponent extends AbstractController
{
    use DefaultActionTrait;

    /** Range in weeks from today. */
    #[LiveProp(writable: true, url: true)]
    public int $weeks = 4;

    /** @var list<int> */
    public array $rangeOptions = [1, 2, 4, 12];

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly Security $security,
    ) {}

    /**
     * @return array<string, list<array{lesson: Lesson, booking: Booking, cancelled: bool}>>
     */
    public function getDays(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $weeks = in_array($this->weeks, $this->rangeOptions, true) ? $this->weeks : 4;
        $now = Clock::get()->now();
        $until = $now->modify(sprintf('+%d weeks', $weeks));

        $rows = [];
        foreach ($this->bookingRepository->findVisibleForUser($user) as $booking) {
            foreach ($booking->getLessons() as $lesson) {
                if ($lesson->schedule <= $now || $lesson->schedule > $until) {
                    continue;
                }

                $rows[] = [
                    'lesson' => $lesson,
                    'booking' => $booking,
                    'cancelled' => $booking->getLessonsMap()->isCancelledLesson($lesson->getId()),
                ];
            }
        }

        usort($rows, static fn(array $a, array $b): int => $a['lesson']->schedule <=> $b['lesson']->schedule);

        $days = [];
        foreach ($rows as $row) {
            $days[$row['lesson']->schedule->format('Y-m-d')][] = $row;
        }

        return $days;
    }
}

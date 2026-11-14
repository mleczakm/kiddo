<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;

final readonly class StaffLessonPresenter
{
    public function __construct(
        private LessonPresenter $presenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function participantRow(Booking $booking, Lesson $lesson, bool $cancelled): array
    {
        $user = $booking->getUser();

        return [
            'booking_id' => (string) $booking->getId(),
            'participant' => $booking->getChild()?->getName() ?? $user->getName(),
            'child_name' => $booking->getChild()?->getName(),
            'parent_name' => $user->getName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone() !== null ? (string) $user->getPhone() : null,
            'status' => $booking->getStatus(),
            'payment_status' => $booking->getPayment()?->getStatus(),
            'occurrence_cancelled' => $cancelled,
            'occurrence_rescheduled' => $booking->isLessonRescheduled($lesson),
            'notes' => $booking->getNotes(),
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws \LogicException
     */
    public function lessonRow(ChatActor $actor, Lesson $lesson): array
    {
        $active = 0;
        foreach ($lesson->getBookings() as $booking) {
            if ($this->isOccurrenceCancelled($booking, $lesson)) {
                continue;
            }
            ++$active;
        }

        return [
            ...$this->presenter->lesson($lesson),
            'bookings_count' => $lesson->getBookings()->count(),
            'active_participants' => $active,
            'is_mine' => $this->isMine($actor, $lesson),
            'instructors' => array_map(
                static fn(User $instructor): string => $instructor->getName(),
                $lesson->getAllInstructors(),
            ),
        ];
    }

    /** @throws \LogicException */
    private function isMine(ChatActor $actor, Lesson $lesson): bool
    {
        if ($actor->isGuest()) {
            return false;
        }
        $userId = $actor->requireUser()->getId();
        foreach ($lesson->getAllInstructors() as $instructor) {
            if ($instructor->getId() === $userId) {
                return true;
            }
        }

        return false;
    }

    public function isOccurrenceCancelled(Booking $booking, Lesson $lesson): bool
    {
        return $booking->getStatus() === Booking::STATUS_CANCELLED || $booking->isLessonCancelled($lesson);
    }
}

<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\Repository\LessonRepository;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminCalendarLessonsComponent
{
    use DefaultActionTrait;

    public function __construct(
        private readonly LessonRepository $lessonRepository,
    ) {}

    /**
     * @return list<array{date: \DateTimeImmutable, lessons: list<array<string, string>>}>
     */
    public function getCalendarDays(): array
    {
        $now = Clock::get()->now();
        $lessons = $this->lessonRepository->findUpcomingWithBookings($now, 15);

        $days = [];
        foreach ($lessons as $lesson) {
            $dayKey = $lesson->getMetadata()->schedule->format('Y-m-d');
            $days[$dayKey] ??= [
                'date' => $lesson->getMetadata()->schedule,
                'lessons' => [],
            ];
            $days[$dayKey]['lessons'][] = $this->formatLesson($lesson);
        }

        return array_values($days);
    }

    /**
     * @return array<string, string>
     */
    private function formatLesson(Lesson $lesson): array
    {
        $metadata = $lesson->getMetadata();
        $end = $metadata->schedule->modify('+' . $metadata->duration . ' minutes');

        $instructors = array_map(
            static fn($instructor): string => $instructor->getName(),
            $lesson->getAllInstructors(),
        );

        $capacity = $metadata->capacity;
        $booked = $capacity - $lesson->getAvailableSpots();

        return [
            'time' => $metadata->schedule->format('H:i') . ' - ' . $end->format('H:i'),
            'title' => $metadata->title,
            'instructor' => $instructors === [] ? 'Brak przypisanego instruktora' : implode(', ', $instructors),
            'age' => $metadata->ageRange->max !== null
                ? $metadata->ageRange->min . '-' . $metadata->ageRange->max . ' lat'
                : $metadata->ageRange->min . '+ lat',
            'capacity' => $booked . '/' . $capacity,
            'status' => $lesson->status !== 'active'
                ? 'Odwołane'
                : ($lesson->canBeBooked() ? 'Odbędzie się' : 'Brak miejsc'),
        ];
    }
}

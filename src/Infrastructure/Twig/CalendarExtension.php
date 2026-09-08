<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Application\Calendar\LessonCalendarFactory;
use App\Entity\Lesson;
use Twig\Attribute\AsTwigFunction;

readonly class CalendarExtension
{
    public function __construct(
        private LessonCalendarFactory $calendarFactory,
    ) {}

    /**
     * "Add to Google Calendar" deep link for a single lesson — used on the
     * payment screens so a customer can save the class while paying.
     *
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    #[AsTwigFunction('lesson_google_calendar_url')]
    public function lessonGoogleCalendarUrl(Lesson $lesson): string
    {
        return $this->calendarFactory->googleCalendarUrl($lesson);
    }
}

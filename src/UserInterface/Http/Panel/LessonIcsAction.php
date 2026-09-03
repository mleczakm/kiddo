<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Panel;

use App\Entity\Lesson;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Per-lesson "add to calendar" download. Only serves lessons the current user
 * actually has a (non-cancelled) booking for.
 */
final class LessonIcsAction extends AbstractController
{
    /**
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws \InvalidArgumentException
     */
    #[Route(path: [
        'en' => '/account/schedule/{lesson}.ics',
        'pl' => '/panel/zajecia/{lesson}.ics',
    ], name: 'panel_lesson_ics')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        string $lesson,
        LessonRepository $lessonRepository,
        BookingRepository $bookingRepository,
        #[CurrentUser]
        User $user,
    ): Response {
        $entity = $lessonRepository->find($lesson);
        if (!$entity instanceof Lesson) {
            throw $this->createNotFoundException();
        }

        if ($bookingRepository->findForUserAndLesson($user, $entity) === []) {
            throw $this->createNotFoundException();
        }

        $utc = new \DateTimeZone('UTC');
        $start = $entity->schedule;
        $end = $start->modify('+' . $entity->getMetadata()->duration . ' minutes');
        $stamp = new \DateTimeImmutable('now', $utc);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Warsztatownia Sensoryczna//Panel//PL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:lesson-' . (string) $entity->getId() . '@warsztatowniasensoryczna.pl',
            'DTSTAMP:' . $stamp->format('Ymd\THis\Z'),
            'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z'),
            'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z'),
            'SUMMARY:' . $this->escapeIcs($entity->getMetadata()->title),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return new Response(implode("\r\n", $lines) . "\r\n", Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="lekcja-' . $start->format('Y-m-d') . '.ics"',
        ]);
    }

    private function escapeIcs(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }
}

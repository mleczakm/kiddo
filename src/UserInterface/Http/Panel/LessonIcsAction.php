<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Panel;

use App\Application\Calendar\LessonCalendarFactory;
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
        LessonCalendarFactory $calendarFactory,
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

        return new Response($calendarFactory->icsForLesson($entity), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="lekcja-' . $entity->schedule->format('Y-m-d') . '.ics"',
        ]);
    }
}

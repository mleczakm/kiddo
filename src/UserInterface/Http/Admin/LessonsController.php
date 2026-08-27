<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Application\Service\HolidayChecker;
use App\Entity\Lesson;
use App\Infrastructure\Symfony\Security\Voter\LessonVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LessonsController extends AbstractController
{
    #[IsGranted('ROLE_MANAGE_LESSONS')]
    #[Route('/admin/zajecia/{id}', name: 'app_admin_lesson_view', requirements: [
        'id' => '[A-Za-z0-9]+',
    ])]
    public function view(Lesson $lesson, HolidayChecker $holidayChecker): Response
    {
        if (!$this->isGranted(LessonVoter::VIEW, $lesson)) {
            throw $this->createNotFoundException();
        }

        $series = $lesson->getSeries();
        $prev = $series?->getLessonsLt($lesson);
        $next = $series?->getLessonsGt($lesson);

        return $this->render('admin/lessons/view.html.twig', [
            'lesson' => $lesson,
            'prevLesson' => $prev,
            'nextLesson' => $next,
            'holidayNames' => $holidayChecker->holidayNamesAt($lesson->schedule),
        ]);
    }

    #[IsGranted('ROLE_MANAGE_LESSONS')]
    #[Route(
        '/admin/zajecia/{id}/toggle',
        name: 'app_admin_lesson_toggle',
        methods: ['POST'],
        requirements: [
            'id' => '[A-Za-z0-9]+',
        ],
    )]
    public function toggle(Lesson $lesson, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isGranted(LessonVoter::MANAGE, $lesson)) {
            throw $this->createNotFoundException();
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle_lesson_' . $lesson->getId(), (string) $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_lesson_view', [
                'id' => (string) $lesson->getId(),
            ]);
        }

        $lesson->status = $lesson->status === 'active' ? 'cancelled' : 'active';
        $entityManager->flush();
        $this->addFlash('success', $lesson->status === 'active' ? 'Lesson activated.' : 'Lesson cancelled.');

        return $this->redirectToRoute('app_admin_lesson_view', [
            'id' => (string) $lesson->getId(),
        ]);
    }
}

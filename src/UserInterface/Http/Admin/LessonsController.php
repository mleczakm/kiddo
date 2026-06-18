<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LessonsController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/zajecia', name: 'app_admin_lessons')]
    public function index(): Response
    {
        return $this->render('admin/lessons/index.html.twig');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/zajecia/{id}', name: 'app_admin_lesson_view', requirements: [
        'id' => '[A-Za-z0-9]+',
    ])]
    public function view(Lesson $lesson): Response
    {
        $series = $lesson->getSeries();
        $prev = $series?->getLessonsLt($lesson);
        $next = $series?->getLessonsGt($lesson);

        return $this->render('admin/lessons/view.html.twig', [
            'lesson' => $lesson,
            'prevLesson' => $prev,
            'nextLesson' => $next,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/zajecia/{id}/toggle', name: 'app_admin_lesson_toggle', methods: ['POST'], requirements: [
        'id' => '[A-Za-z0-9]+',
    ])]
    public function toggle(
        Lesson $lesson,
        Request $request,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $token = $request->request->get('_token');
        if (! $this->isCsrfTokenValid('toggle_lesson_' . $lesson->getId(), (string) $token)) {
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

<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\Lesson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminAction extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'activeTab' => 'dashboard',
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/zajecia', name: 'app_admin_lessons')]
    public function lessons(): Response
    {
        return $this->render('admin/lessons.html.twig', [
            'activeTab' => 'lessons',
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/harmonogram', name: 'app_admin_schedule')]
    public function schedule(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'activeTab' => 'schedule',
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/platnosci', name: 'app_admin_transfers')]
    public function transfers(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'activeTab' => 'transfers',
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/rezerwacje', name: 'app_admin_bookings')]
    public function bookings(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'activeTab' => 'bookings',
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/uzytkownicy', name: 'app_admin_users')]
    public function users(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'activeTab' => 'users',
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/uzytkownicy/{id}', name: 'app_admin_user_view', requirements: [
        'id' => '\\d+',
    ])]
    public function userView(User $user): Response
    {
        return $this->render('admin/user.html.twig', [
            'activeTab' => 'users',
            'user' => $user,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/wiadomosci', name: 'app_admin_messages')]
    public function messages(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'activeTab' => 'messages',
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/zajecia/{id}', name: 'app_admin_lesson_view', requirements: [
        'id' => '[A-Za-z0-9]+',
    ])]
    public function lessonView(Lesson $lesson): Response
    {
        $series = $lesson->getSeries();
        $prev = $series?->getLessonsLt($lesson);
        $next = $series?->getLessonsGt($lesson);

        return $this->render('admin/lesson_show.html.twig', [
            'activeTab' => 'lessons',
            'lesson' => $lesson,
            'prevLesson' => $prev,
            'nextLesson' => $next,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/zajecia/{id}/toggle', name: 'app_admin_lesson_toggle', methods: ['POST'], requirements: [
        'id' => '[A-Za-z0-9]+',
    ])]
    public function toggleLessonStatus(
        Lesson $lesson,
        Request $request,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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

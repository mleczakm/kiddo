<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        return $this->render('admin/dashboard.html.twig', [
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
}

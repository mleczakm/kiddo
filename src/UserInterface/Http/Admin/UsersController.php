<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UsersController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/uzytkownicy', name: 'app_admin_users')]
    public function index(): Response
    {
        return $this->render('admin/users/index.html.twig');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/uzytkownicy/{id}', name: 'app_admin_user_view', requirements: [
        'id' => '\d+',
    ])]
    public function view(User $user): Response
    {
        return $this->render('admin/users/view.html.twig', [
            'user' => $user,
        ]);
    }
}

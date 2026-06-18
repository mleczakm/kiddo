<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SettingsController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/ustawienia', name: 'app_admin_settings')]
    public function index(): Response
    {
        return $this->render('admin/settings/index.html.twig', [
            'settingsTab' => 'general',
        ]);
    }
}

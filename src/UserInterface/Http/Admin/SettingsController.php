<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SettingsController extends AbstractController
{
    /** @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException */
    #[IsGranted('ROLE_SETTINGS')]
    #[Route('/admin/ustawienia', name: 'app_admin_settings')]
    public function index(Request $request): Response
    {
        return $this->render('admin/settings/index.html.twig', [
            'settingsTab' => $request->query->get('tab') === 'recommendations' ? 'recommendations' : 'roles',
        ]);
    }
}

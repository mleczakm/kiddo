<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ScheduleController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/harmonogram', name: 'app_admin_schedule')]
    public function index(): Response
    {
        return $this->render('admin/schedule/index.html.twig');
    }
}

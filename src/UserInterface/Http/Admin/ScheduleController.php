<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ScheduleController extends AbstractController
{
    #[Route('/admin/harmonogram', name: 'app_admin_schedule')]
    public function index(): Response
    {
        // Merged Warsztaty (Series) + Zajęcia (Lesson) view: an admin with
        // either capability can open it — the component itself scopes what
        // a ROLE_HOST-only user (ROLE_MANAGE_LESSONS) sees and can act on.
        if (! $this->isGranted('ROLE_MANAGE_SCHEDULE') && ! $this->isGranted('ROLE_MANAGE_LESSONS')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('admin/schedule/index.html.twig');
    }
}

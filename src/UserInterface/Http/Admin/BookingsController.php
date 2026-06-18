<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class BookingsController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/rezerwacje', name: 'app_admin_bookings')]
    public function index(): Response
    {
        return $this->render('admin/bookings/index.html.twig');
    }
}

<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TransfersController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/platnosci', name: 'app_admin_transfers')]
    public function index(): Response
    {
        return $this->render('admin/transfers/index.html.twig');
    }
}

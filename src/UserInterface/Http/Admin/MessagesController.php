<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MessagesController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/wiadomosci', name: 'app_admin_messages')]
    public function index(): Response
    {
        return $this->render('admin/messages/index.html.twig');
    }
}

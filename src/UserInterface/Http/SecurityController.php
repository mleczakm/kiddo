<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function form(): Response
    {
        // If the user is already authenticated, send them to their dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('user_dashboard');
        }
        return $this->render('login.html.twig');
    }

    #[Route('/login_check', name: 'login_check')]
    public function check(): never
    {
        throw new LogicException('This code should never be reached');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new LogicException('This code should never be reached');
    }
}

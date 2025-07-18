<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegisterUserAction extends AbstractController
{
    #[Route('/register', name: 'user_register')]
    public function form(): Response
    {
        return $this->render('register.html.twig');
    }
}

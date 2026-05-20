<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserDashboardAction extends AbstractController
{
    public function __construct(
    ) {}

    #[IsGranted('ROLE_USER')]
    #[Route('/panel', name: 'user_dashboard')]
    public function __invoke(): Response
    {
        return new RedirectResponse($this->generateUrl('homepage'));
    }
}

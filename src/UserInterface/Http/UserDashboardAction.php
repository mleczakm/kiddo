<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Tenant\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserDashboardAction extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    #[IsGranted('ROLE_USER')]
    #[Route('/panel', name: 'user_dashboard')]
    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->getTenant();
        // For Class Council tenant, redirect to dedicated dashboard
        if ($tenant && preg_match('/classpay/i', $tenant->getName())) {
            return $this->redirectToRoute('cc_dashboard');
        }

        // Default fallback: go to homepage or an existing dashboard if any
        return new RedirectResponse($this->generateUrl('homepage'));
    }
}

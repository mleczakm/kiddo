<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Admin;

use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PricingController extends AbstractController
{
    public function __construct(
        private readonly FeatureManager $featureManager,
    ) {}

    #[IsGranted('ROLE_MANAGE_PRICING')]
    #[Route('/admin/cennik', name: 'app_admin_pricing')]
    public function index(): Response
    {
        if (!$this->featureManager->isEnabled('pricing_admin')) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/pricing/index.html.twig');
    }
}

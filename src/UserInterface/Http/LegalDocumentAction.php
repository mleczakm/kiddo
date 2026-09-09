<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalDocumentAction extends AbstractController
{
    #[Route('/regulamin', name: 'legal_app_terms', priority: 20, methods: ['GET'])]
    public function appTerms(): Response
    {
        return $this->render('legal/app_terms.html.twig');
    }

    #[Route('/regulamin-zajec', name: 'legal_classes_terms', priority: 20, methods: ['GET'])]
    public function classesTerms(): Response
    {
        return $this->render('legal/classes_terms.html.twig');
    }

    #[Route('/polityka-prywatnosci', name: 'legal_privacy', priority: 20, methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }
}

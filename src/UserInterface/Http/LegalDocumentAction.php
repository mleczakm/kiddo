<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\LegalDocumentType;
use App\Infrastructure\Doctrine\Repository\LegalDocumentVersionRepository;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

final class LegalDocumentAction extends AbstractController
{
    public function __construct(
        private readonly LegalDocumentVersionRepository $versionRepository,
        private readonly FeatureManager $featureManager,
    ) {}

    /**
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    #[Route('/regulamin', name: 'legal_app_terms', priority: 20, methods: ['GET'])]
    public function appTerms(Request $request): Response
    {
        return $this->renderDocument($request, LegalDocumentType::APP_TERMS, 'legal/app_terms.html.twig');
    }

    /**
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    #[Route('/regulamin-zajec', name: 'legal_classes_terms', priority: 20, methods: ['GET'])]
    public function classesTerms(Request $request): Response
    {
        return $this->renderDocument(
            $request,
            LegalDocumentType::CLASSES_TERMS_GENERAL,
            'legal/classes_terms.html.twig',
        );
    }

    /**
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    #[Route('/polityka-prywatnosci', name: 'legal_privacy', priority: 20, methods: ['GET'])]
    public function privacyPolicy(Request $request): Response
    {
        return $this->renderDocument($request, LegalDocumentType::PRIVACY, 'legal/privacy.html.twig');
    }

    /**
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function renderDocument(Request $request, LegalDocumentType $type, string $fallbackTemplate): Response
    {
        if (!$this->featureManager->isEnabled('legal_documents')) {
            return $this->render($fallbackTemplate);
        }

        $now = Clock::get()->now();
        $currentVersion = $this->versionRepository->findCurrent($type, $now);
        $requestedVersion = $request->query->get('v');
        $requestedVersionId = \is_string($requestedVersion) ? $requestedVersion : '';
        $selectedVersion = $currentVersion;

        if ($requestedVersionId === '' && $currentVersion === null) {
            return $this->render($fallbackTemplate);
        }

        if ($requestedVersionId !== '') {
            if (!Ulid::isValid($requestedVersionId)) {
                throw $this->createNotFoundException();
            }

            try {
                $id = Ulid::fromString($requestedVersionId);
            } catch (\InvalidArgumentException) {
                throw $this->createNotFoundException();
            }

            $selectedVersion = $this->versionRepository->findPublicVersion($type, $id, $now);
            if ($selectedVersion === null) {
                throw $this->createNotFoundException();
            }
        }

        if ($selectedVersion === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('legal/file_document.html.twig', [
            'documentType' => $type,
            'routeName' => $type->routeName(),
            'selectedVersion' => $selectedVersion,
            'currentVersion' => $currentVersion,
            'versions' => $this->versionRepository->findPublicVersions($type, $now),
        ]);
    }
}

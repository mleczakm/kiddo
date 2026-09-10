<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\LegalDocumentType;
use App\Infrastructure\Doctrine\Repository\LegalDocumentVersionRepository;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Component\Clock\Clock;

final readonly class ConsentRequirements
{
    public function __construct(
        private FeatureManager $featureManager,
        private LegalDocumentVersionRepository $versionRepository,
    ) {}

    public function isEnabled(): bool
    {
        return $this->featureManager->isEnabled('consents');
    }

    public function isRegistrationAcceptanceRequired(): bool
    {
        // Both flags must be on: `consents` turns enforcement on, `legal_documents`
        // makes the versioned pages the ones actually served. Enforcing acceptance
        // of a versioned document while /regulamin still shows the static fallback
        // would be incoherent.
        if (!$this->isEnabled() || !$this->featureManager->isEnabled('legal_documents')) {
            return false;
        }

        $now = Clock::get()->now();

        return (
            $this->versionRepository->findCurrent(LegalDocumentType::APP_TERMS, $now) !== null
            && $this->versionRepository->findCurrent(LegalDocumentType::PRIVACY, $now) !== null
        );
    }

    public function isCheckoutAcceptanceRequired(): bool
    {
        if (!$this->isRegistrationAcceptanceRequired()) {
            return false;
        }

        return (
            $this->versionRepository->findCurrent(LegalDocumentType::CLASSES_TERMS_GENERAL, Clock::get()->now())
            !== null
        );
    }
}

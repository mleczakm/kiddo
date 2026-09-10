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
        if (!$this->isEnabled()) {
            return false;
        }

        $now = Clock::get()->now();

        return (
            $this->versionRepository->findCurrent(LegalDocumentType::APP_TERMS, $now) !== null
            && $this->versionRepository->findCurrent(LegalDocumentType::PRIVACY, $now) !== null
        );
    }
}

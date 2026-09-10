<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\LegalDocumentVersionRepository;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use Symfony\Component\Clock\Clock;

/**
 * Read side of the consent register: answers "does this user hold a still-valid
 * acceptance?" without touching the write path. Split out of ConsentRecorder so
 * neither class carries the whole register's branching.
 */
final readonly class ConsentStatusReader
{
    public function __construct(
        private UserConsentRepository $consentRepository,
        private LegalDocumentVersionRepository $versionRepository,
    ) {}

    public function hasCurrent(User $user, ConsentType $type): bool
    {
        $documentType = $type->documentType();
        $consent = $this->consentRepository->findLatestActive($user, $type, $documentType);
        if ($consent === null) {
            return false;
        }
        if ($documentType === null) {
            return true;
        }

        $currentVersion = $this->versionRepository->findCurrent($documentType, Clock::get()->now());

        return $currentVersion !== null && $consent->getDocumentVersion()?->getId()->equals($currentVersion->getId());
    }

    /** @return list<LegalDocumentType> */
    public function outdatedDocuments(User $user): array
    {
        $outdated = [];
        foreach ([ConsentType::APP_TERMS, ConsentType::PRIVACY, ConsentType::CLASSES_TERMS] as $type) {
            $documentType = $type->documentType();
            if ($documentType === null) {
                continue;
            }

            $currentVersion = $this->versionRepository->findCurrent($documentType, Clock::get()->now());
            if ($currentVersion !== null && !$this->hasCurrent($user, $type)) {
                $outdated[] = $documentType;
            }
        }

        return $outdated;
    }
}

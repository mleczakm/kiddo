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
    /**
     * Account-scoped documents (as opposed to CLASSES_TERMS, which is re-accepted
     * per booking/checkout rather than via the panel banner).
     */
    private const array ACCOUNT_DOCUMENTS = [LegalDocumentType::APP_TERMS, LegalDocumentType::PRIVACY];

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

    /**
     * Every managed document whose current published version the user has not
     * accepted - used by the change-notification check and the reader's tests.
     *
     * @return list<LegalDocumentType>
     */
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

    /**
     * Account-level documents the panel banner should ask the user to accept:
     * the current version of the Terms or Privacy Policy is published and the
     * user has not accepted it - whether because it changed under them or
     * because they registered before acceptance was enforced.
     *
     * @return list<LegalDocumentType>
     */
    public function accountDocumentsToAccept(User $user): array
    {
        $pending = [];
        $now = Clock::get()->now();
        foreach (self::ACCOUNT_DOCUMENTS as $documentType) {
            if ($this->versionRepository->findCurrent($documentType, $now) === null) {
                continue;
            }
            if (!$this->hasCurrent($user, $documentType->consentType())) {
                $pending[] = $documentType;
            }
        }

        return $pending;
    }
}

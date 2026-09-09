<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use App\Entity\UserConsent;
use App\Infrastructure\Doctrine\Repository\LegalDocumentVersionRepository;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;

final readonly class ConsentRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserConsentRepository $consentRepository,
        private LegalDocumentVersionRepository $versionRepository,
        private RequestContext $requestContext,
        private LoggerInterface $logger,
    ) {}

    /**
     * Consent audit logging must never interrupt the primary user action.
     * Failures are reported and represented by a null return value.
     */
    public function record(
        User $user,
        ConsentType $type,
        ConsentSource $source,
        ConsentEvidence $evidence,
    ): ?UserConsent {
        try {
            $evidence = $this->resolveDocumentVersion($evidence);
            if ($evidence === null) {
                return null;
            }

            $consent = new UserConsent($user, $type, $source, $evidence, $this->requestContext);
            $this->entityManager->persist($consent);
            $this->entityManager->flush();

            return $consent;
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to record user consent.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
                'consent_type' => $type->value,
                'consent_source' => $source->value,
            ]);

            return null;
        }
    }

    public function revoke(User $user, ConsentType $type, ConsentSource $source): bool
    {
        try {
            $activeConsents = $this->consentRepository->findActiveByType($user, $type);
            if ($activeConsents === []) {
                return false;
            }

            foreach ($activeConsents as $consent) {
                $consent->revoke();
            }
            $this->entityManager->flush();

            $this->logger->info('User consent revoked.', [
                'user_id' => $user->getId(),
                'consent_type' => $type->value,
                'consent_source' => $source->value,
            ]);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to revoke user consent.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
                'consent_type' => $type->value,
                'consent_source' => $source->value,
            ]);

            return false;
        }
    }

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

    /** @throws \InvalidArgumentException */
    private function resolveDocumentVersion(ConsentEvidence $evidence): ?ConsentEvidence
    {
        $documentType = $evidence->requestedDocumentType();
        if ($evidence->documentVersion() !== null || $documentType === null) {
            return $evidence;
        }

        $version = $this->versionRepository->findCurrent($documentType, Clock::get()->now());
        if ($version === null) {
            $this->logger->warning('Consent was not recorded because the legal document has no current version.', [
                'document_type' => $documentType->value,
            ]);

            return null;
        }

        return $evidence->withDocumentVersion($version);
    }
}

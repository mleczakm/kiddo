<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Application\Repository\LegalDocumentVersionRepositoryInterface;
use App\Application\Repository\UserConsentRepositoryInterface;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use App\Entity\UserConsent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;

/**
 * Write side of the consent register. Only ever reached from a command handler
 * (RecordConsentsHandler / RevokeConsentHandler): the message bus wraps the
 * handler in a Doctrine transaction, so this class only persists - it never
 * flushes and never swallows failures. A failing acceptance set therefore rolls
 * back whole, and the dispatching code decides whether that failure may surface.
 */
final readonly class ConsentRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserConsentRepositoryInterface $consentRepository,
        private LegalDocumentVersionRepositoryInterface $versionRepository,
        private RequestContext $requestContext,
        private LoggerInterface $logger,
    ) {}

    /**
     * Persists a complete acceptance set, so a registration or checkout cannot
     * leave only part of its required evidence behind. Returns an empty list
     * without persisting anything when a referenced legal document has no
     * current version yet.
     *
     * @return list<UserConsent>
     * @throws \InvalidArgumentException when evidence is malformed or its
     *     resolved document type does not match the consent type - a bug that
     *     must roll the surrounding transaction back rather than persist junk.
     */
    public function recordMany(User $user, ConsentSource $source, ConsentGrant ...$grants): array
    {
        if ($grants === []) {
            return [];
        }

        $resolvedGrants = [];
        foreach ($grants as $grant) {
            $evidence = $this->resolveDocumentVersion($grant->evidence);
            if ($evidence === null) {
                return [];
            }

            $resolvedGrants[] = new ConsentGrant($grant->type, $evidence);
        }

        $consents = [];
        foreach ($resolvedGrants as $grant) {
            $consent = new UserConsent($user, $grant->type, $source, $grant->evidence, $this->requestContext);
            $this->entityManager->persist($consent);
            $consents[] = $consent;
        }

        return $consents;
    }

    /** @throws \UnexpectedValueException */
    public function revoke(User $user, ConsentType $type, ConsentSource $source): bool
    {
        $activeConsents = $this->consentRepository->findActiveByType($user, $type);
        if ($activeConsents === []) {
            return false;
        }

        foreach ($activeConsents as $consent) {
            $consent->revoke();
        }

        $this->logger->info('User consent revoked.', [
            'user_id' => $user->getId(),
            'consent_type' => $type->value,
            'consent_source' => $source->value,
        ]);

        return true;
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

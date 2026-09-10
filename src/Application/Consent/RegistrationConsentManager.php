<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RegistrationConsentManager
{
    public function __construct(
        private ConsentRecorder $consentRecorder,
        private ConsentRequirements $consentRequirements,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {}

    public function isLegalAcceptanceRequired(): bool
    {
        try {
            return $this->consentRequirements->isRegistrationAcceptanceRequired();
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to determine registration consent requirements.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function recordLegalAcceptance(User $user): void
    {
        if (!$this->isLegalAcceptanceRequired()) {
            return;
        }

        try {
            $acceptanceText = $this->translator->trans('form.register.accept_terms_text');
            $this->consentRecorder->record(
                $user,
                ConsentType::APP_TERMS,
                ConsentSource::REGISTRATION,
                ConsentEvidence::currentDocument(LegalDocumentType::APP_TERMS, $acceptanceText),
            );
            $this->consentRecorder->record(
                $user,
                ConsentType::PRIVACY,
                ConsentSource::REGISTRATION,
                ConsentEvidence::currentDocument(LegalDocumentType::PRIVACY, $acceptanceText),
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to prepare registration legal consent evidence.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
            ]);
        }
    }

    public function recordMarketingAcceptance(User $user): void
    {
        try {
            if (!$this->consentRequirements->isEnabled()) {
                return;
            }

            $this->consentRecorder->record(
                $user,
                ConsentType::MARKETING_EMAIL,
                ConsentSource::REGISTRATION,
                ConsentEvidence::statement($this->translator->trans('form.register.newsletter')),
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to prepare registration marketing consent evidence.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Application\Command\RecordConsents;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RegistrationConsentManager
{
    public function __construct(
        private MessageBusInterface $commandBus,
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
            $this->commandBus->dispatch(new RecordConsents($user, ConsentSource::REGISTRATION, [
                new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                    LegalDocumentType::APP_TERMS,
                    $acceptanceText,
                )),
                new ConsentGrant(ConsentType::PRIVACY, ConsentEvidence::currentDocument(
                    LegalDocumentType::PRIVACY,
                    $acceptanceText,
                )),
            ]));
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to record registration legal consent.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
            ]);
        }
    }
}

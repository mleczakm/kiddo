<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Application\Command\RecordConsents;
use App\Application\Command\RevokeConsent;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MarketingConsentManager
{
    public const string VERSION = '2026-09-10';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly ConsentRequirements $consentRequirements,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {}

    public function isEnabled(): bool
    {
        try {
            return $this->consentRequirements->isEnabled();
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to determine marketing consent requirements.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function text(): string
    {
        try {
            return $this->translator->trans('newsletter.marketing_consent_text');
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error('Unable to translate the marketing consent statement.', [
                'exception' => $exception,
            ]);

            return 'I consent to receiving Kiddo marketing information by email.';
        }
    }

    public function grant(User $user, ConsentSource $source): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $this->commandBus->dispatch(new RecordConsents($user, $source, [
                new ConsentGrant(ConsentType::MARKETING_EMAIL, ConsentEvidence::statement($this->text())),
            ]));
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to record marketing consent.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
                'source' => $source->value,
            ]);
        }
    }

    public function revoke(User $user, ConsentSource $source): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $this->commandBus->dispatch(new RevokeConsent($user, ConsentType::MARKETING_EMAIL, $source));
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to revoke marketing consent.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
                'source' => $source->value,
            ]);
        }
    }
}

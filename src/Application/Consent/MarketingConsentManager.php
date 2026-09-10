<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Not final: NewsletterSubscriptionManagerTest doubles this to keep the
 * newsletter unit tests off the message bus.
 */
class MarketingConsentManager
{
    public const string VERSION = '2026-09-10';

    public function __construct(
        private readonly ConsentDispatcher $dispatcher,
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
            $grant = new ConsentGrant(ConsentType::MARKETING_EMAIL, ConsentEvidence::statement($this->text()));
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error('Unable to build marketing consent evidence.', ['exception' => $exception]);

            return;
        }

        $this->dispatcher->record($user, $source, $grant);
    }

    public function revoke(User $user, ConsentSource $source): void
    {
        if ($this->isEnabled()) {
            $this->dispatcher->revoke($user, ConsentType::MARKETING_EMAIL, $source);
        }
    }
}

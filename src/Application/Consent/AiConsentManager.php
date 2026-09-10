<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AiConsentManager
{
    public const string VERSION = '2026-09-10';

    public function __construct(
        private ConsentDispatcher $dispatcher,
        private ConsentRequirements $consentRequirements,
        private UserConsentRepository $consentRepository,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {}

    public function isRequired(): bool
    {
        try {
            return $this->consentRequirements->isEnabled();
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to determine AI consent requirements.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function hasCurrent(User $user): bool
    {
        $consent = $this->consentRepository->findLatestActive($user, ConsentType::AI_USAGE);

        return $consent?->getContext() === 'ai_consent:' . self::VERSION;
    }

    public function allowsSession(?User $user, string $requestBody): bool
    {
        if (!$this->isRequired() || $user !== null && $this->hasCurrent($user)) {
            return true;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($requestBody === '' ? '{}' : $requestBody, true);
        if (
            !is_array($payload)
            || ($payload['aiConsent'] ?? null) !== true
            || ($payload['aiConsentVersion'] ?? null) !== self::VERSION
        ) {
            return false;
        }

        if ($user !== null) {
            $this->grant($user);
        }

        return true;
    }

    public function grant(User $user): void
    {
        try {
            $grant = new ConsentGrant(ConsentType::AI_USAGE, ConsentEvidence::statement(
                $this->text(),
                'ai_consent:' . self::VERSION,
            ));
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error('Unable to build AI consent evidence.', ['exception' => $exception]);

            return;
        }

        $this->dispatcher->record($user, ConsentSource::CHAT_ONBOARDING, $grant);
    }

    private function text(): string
    {
        try {
            return $this->translator->trans('chat.ai_consent.statement');
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error('Unable to translate the AI consent statement.', [
                'exception' => $exception,
            ]);

            return 'I understand that AI responses may be incorrect and require verification.';
        }
    }
}

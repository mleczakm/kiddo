<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Application\Repository\UserConsentRepositoryInterface;
use App\Entity\Child;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Records the account holder's declaration that they are the child's parent or
 * legal guardian and may provide the child's data. The app stores only a name
 * and birth date for a child (no health data), so this is a plain statement,
 * not a special-category consent.
 */
final readonly class ChildConsentManager
{
    public function __construct(
        private ConsentDispatcher $dispatcher,
        private ConsentRequirements $consentRequirements,
        private UserConsentRepositoryInterface $consentRepository,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {}

    public function isRequired(): bool
    {
        try {
            return $this->consentRequirements->isEnabled();
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to determine child consent requirements.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function hasDeclaration(User $user): bool
    {
        return $this->consentRepository->findLatestActive($user, ConsentType::CHILD_DATA_GUARDIAN) !== null;
    }

    public function recordDeclaration(User $user, Child $child, ConsentSource $source): void
    {
        if (!$this->isRequired()) {
            return;
        }

        try {
            $grant = new ConsentGrant(ConsentType::CHILD_DATA_GUARDIAN, ConsentEvidence::statement(
                $this->translator->trans('profile.children.guardian_declaration_text'),
                sprintf('child:%s', $child->getId()),
            ));
        } catch (\InvalidArgumentException $exception) {
            $this->logger->error('Unable to build child guardian declaration evidence.', [
                'exception' => $exception,
                'child_id' => (string) $child->getId(),
            ]);

            return;
        }

        $this->dispatcher->record($user, $source, $grant);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\NotifyLegalDocumentChange;
use App\Application\Legal\LegalChangeAnnouncer;
use App\Application\Repository\UserRepositoryInterface;
use App\Infrastructure\Doctrine\Repository\LegalDocumentVersionRepository;
use Symfony\Component\Clock\Clock;

/**
 * Emails every account holder and drops an in-app notice when a legal document
 * gets a new version. Idempotent: the version's notifiedAt is stamped once (the
 * bus transaction middleware flushes it) and a re-run returns early. Routed
 * async so a rare regulation change never blocks the admin publish request.
 */
final readonly class NotifyLegalDocumentChangeHandler
{
    public function __construct(
        private LegalDocumentVersionRepository $versionRepository,
        private UserRepositoryInterface $userRepository,
        private LegalChangeAnnouncer $announcer,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function __invoke(NotifyLegalDocumentChange $command): void
    {
        $version = $this->versionRepository->find($command->versionId);
        if ($version === null || $version->getNotifiedAt() !== null) {
            return;
        }

        $this->announcer->announce($version, $this->userRepository->findByRole('ROLE_USER'));

        $version->markNotified(Clock::get()->now());
    }
}

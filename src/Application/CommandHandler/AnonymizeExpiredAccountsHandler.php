<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\AnonymizeExpiredAccounts;
use App\Application\Command\AnonymizeUser;
use App\Application\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Daily sweep. Anonymises accounts past their deletion grace period, one
 * AnonymizeUser message each. A hard cap guards against a bad query or a mass
 * request wave: above it the run aborts and alerts instead of scrubbing.
 */
final readonly class AnonymizeExpiredAccountsHandler
{
    public const int GRACE_DAYS = 14;

    public const int MAX_PER_RUN = 25;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger,
    ) {}

    /** @throws \Symfony\Component\Messenger\Exception\ExceptionInterface */
    public function __invoke(AnonymizeExpiredAccounts $command): void
    {
        $cutoff = Clock::get()->now()->modify(sprintf('-%d days', self::GRACE_DAYS));
        $expired = $this->userRepository->findPendingDeletionBefore($cutoff);

        if ($expired === []) {
            return;
        }

        if (count($expired) > self::MAX_PER_RUN) {
            $this->logger->alert('Account anonymisation sweep aborted: too many accounts due at once.', [
                'due' => count($expired),
                'cap' => self::MAX_PER_RUN,
            ]);

            return;
        }

        foreach ($expired as $user) {
            $userId = $user->getId();
            if ($userId === null) {
                continue;
            }

            $this->commandBus->dispatch(new Envelope(new AnonymizeUser($userId))->with(
                new DispatchAfterCurrentBusStamp(),
            ));
        }
    }
}

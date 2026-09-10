<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Account\UserAnonymizer;
use App\Application\Command\AnonymizeUser;
use App\Application\Repository\UserRepositoryInterface;

final readonly class AnonymizeUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserAnonymizer $anonymizer,
    ) {}

    /** @throws \UnexpectedValueException */
    public function __invoke(AnonymizeUser $command): void
    {
        $user = $this->userRepository->find($command->userId);
        if ($user === null) {
            return;
        }

        $this->anonymizer->anonymize($user);
    }
}

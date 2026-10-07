<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\NewUser;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\NewUserNotifier;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class NewUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private NewUserNotifier $notifier,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function __invoke(NewUser $command): void
    {
        // Async transport serializes the User as a detached instance; reload so Doctrine
        // transaction middleware can flush confirmedAt when the handler succeeds.
        $user = $this->userRepository->find($command->user->getId());
        if ($user === null) {
            return;
        }

        // Prevent duplicate email sending if user is already confirmed
        if ($user->getConfirmedAt() !== null) {
            return;
        }

        $user->setConfirmedAt(Clock::get()->now());

        $this->notifier->notify($user);
    }
}

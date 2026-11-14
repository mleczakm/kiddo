<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Command\Notification\NewUser;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Application\Service\ActivityLogger;
use App\Entity\ActivityType;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AccountAndTransferActivity
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
    ) {}

    /**
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     */
    public function logUserRegistered(NewUser $command): void
    {
        // The handler reloads/confirms the user by id; reload here too so we
        // read the post-handling state (name may have been touched) consistently.
        $user = $this->userRepository->find($command->user->getId());
        if ($user === null) {
            return;
        }

        $userId = $user->getId();
        if ($userId === null) {
            return;
        }

        $this->activityLogger->log(
            type: ActivityType::USER_REGISTERED,
            title: sprintf('Nowy użytkownik: %s', $user->getName()),
            subject: $user,
            summary: $user->getEmail(),
            url: $this->urlGenerator->generate('app_admin_user_view', [
                'id' => $userId,
            ]),
            dedupeKey: 'user_registered:' . $userId,
        );
    }

    /**
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     */
    public function logTransferUnmatched(TransferNotMatchedCommand $command): void
    {
        $transfer = $command->transfer;
        $transferId = $transfer->getId();
        if ($transferId === null) {
            return;
        }

        $this->activityLogger->log(
            type: ActivityType::TRANSFER_UNMATCHED,
            title: 'Nierozpoznany przelew',
            subject: null,
            summary: sprintf('%s — %s (%s)', $transfer->amount, $transfer->getSender(), $transfer->title),
            url: $this->urlGenerator->generate('app_admin_transfers'),
            context: [
                'transferId' => $transferId,
            ],
            dedupeKey: 'transfer_unmatched:' . $transferId,
        );
    }
}

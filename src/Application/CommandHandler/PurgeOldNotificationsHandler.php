<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\PurgeOldNotifications;
use App\Application\Repository\NotificationRepositoryInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeOldNotificationsHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notifications,
    ) {}

    public function __invoke(PurgeOldNotifications $command): void
    {
        $cutoff = Clock::get()->now()->modify('-' . ltrim($command->olderThan, '-'));
        $this->notifications->hardDeleteOlderThan($cutoff);
    }
}

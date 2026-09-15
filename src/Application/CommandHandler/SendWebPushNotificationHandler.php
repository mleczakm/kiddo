<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SendWebPushNotification;
use App\Application\Notification\WebPushSenderInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Entity\User;

readonly class SendWebPushNotificationHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private WebPushSenderInterface $webPush,
    ) {}

    public function __invoke(SendWebPushNotification $command): void
    {
        $user = $this->users->find($command->userId);
        if (!$user instanceof User) {
            return;
        }

        $this->webPush->sendToUser($user, $command->title, $command->body, $command->url);
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Notifier;

use App\Application\Notification\NotificationSenderInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

readonly class SymfonyNotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private NotifierInterface $notifier,
    ) {}

    #[\Override]
    public function send(string $email, string $subject, string $content): void
    {
        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        $this->notifier->send($notification, new Recipient($email));
    }
}

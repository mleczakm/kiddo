<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendVerificationCode;
use App\Application\Notification\NotificationSenderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class SendVerificationCodeHandler
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(SendVerificationCode $command): void
    {
        $this->notificationSender->send(
            $command->email,
            $this->translator->trans('verification_code.subject', [], 'emails'),
            $this->translator->trans(
                'verification_code.content',
                [
                    'code' => $command->code,
                ],
                'emails',
            ),
        );
    }
}

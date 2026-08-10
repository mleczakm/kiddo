<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendVerificationCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class SendVerificationCodeHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(SendVerificationCode $command): void
    {
        $notification = new Notification()
            ->importance('')
            ->subject($this->translator->trans('verification_code.subject', [], 'emails'))
            ->content($this->translator->trans('verification_code.content', [
                'code' => $command->code,
            ], 'emails'));

        $this->notifier->send($notification, new Recipient($command->email));
    }
}

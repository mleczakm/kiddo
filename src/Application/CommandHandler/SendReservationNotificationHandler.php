<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SendReservationNotification;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SendReservationNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(SendReservationNotification $command): void
    {
        $translatorContext = [
            'paymentCode' => $command->paymentCode,
            'paymentAmount' => (string) $command->paymentAmount,
        ];

        $subject = $this->translator->trans('reservation.subject', [], 'emails');
        $content = $this->translator->trans('reservation.content.html', $translatorContext, 'emails');

        $email = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        $recipient = new Recipient($command->email);
        $this->notifier->send($email, $recipient);
    }
}

<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SendReservationNotification;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

readonly class SendReservationNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private TranslatorInterface $translator,
        private Environment $twig
    ) {}

    public function __invoke(SendReservationNotification $command): void
    {
        $translatorContext = [
            'paymentCode' => $command->paymentCode,
            'paymentAmount' => $command->paymentAmount,
            'blikPhoneNumber' => '571 531 213',
            'bankAccountNumber' => '46 2490 0005 0000 4000 1897 5420',
        ];

        $subject = $this->translator->trans('reservation.subject', [], 'emails');
        $content = $this->twig->render('email/reservation.html.twig', $translatorContext);

        $notification = new Notification($subject, ['email'])
            ->importance('')
            ->content($content);

        $recipient = new Recipient($command->email);
        $this->notifier->send($notification, $recipient);
    }
}

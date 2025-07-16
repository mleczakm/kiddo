<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SendLoginNotification;
use App\Entity\User;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SendLoginNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UserProviderInterface $userProvider,
        private LoginLinkHandlerInterface $loginLinkHandler,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(SendLoginNotification $command): void
    {
        try {
            /** @var User $user */
            $user = $this->userProvider->loadUserByIdentifier($command->email);
        } catch (UserNotFoundException) {
            $recipient = new Recipient($command->email);

            $notification = new Notification()
                ->importance('')
                ->subject($this->translator->trans('login_email_missingnotification.subject', [], 'emails'))
                ->content($this->translator->trans('login_email_missingnotification.content', [], 'emails'));

            $this->notifier->send($notification, $recipient);

            return;
        }
        $loginLinkDetails = $this->loginLinkHandler->createLoginLink($user, lifetime: 60 * 60);

        $translatorContext = [
            'name' => $user->getName(),
        ];

        $notification = new LoginLinkNotification(
            $loginLinkDetails,
            $this->translator->trans('login_link.subject', [], 'emails'),
        )->content($this->translator->trans('login_link.content.html', $translatorContext, 'emails'));

        $recipient = new Recipient($command->email);

        $this->notifier->send($notification, $recipient);
    }
}

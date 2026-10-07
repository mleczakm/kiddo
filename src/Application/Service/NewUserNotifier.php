<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Notification\NotificationSenderInterface;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Welcomes a freshly registered user and tells the operational audience
 * (see {@see BookingNotificationRecipients::operational()}) about them.
 */
final readonly class NewUserNotifier
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private TranslatorInterface $translator,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private BookingNotificationRecipients $recipients,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function notify(User $user): void
    {
        $this->sendUserConfirmation($user);
        $this->sendAdminInformation($user);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    private function sendUserConfirmation(User $user): void
    {
        $subject = $this->translator->trans('user.notification.confirmation.subject', [], 'emails');
        $content = $this->translator->trans('user.notification.confirmation.message', [], 'emails');

        $this->notificationSender->send($user->getEmail(), $subject, $content);

        $this->inAppNotifications->notify(
            $user,
            $this->translator->trans('notifications.in_app.new_user.user.title', [], 'messages'),
            $this->translator->trans('notifications.in_app.new_user.user.body', [], 'messages'),
            $this->urlGenerator->generate('dashboard'),
            NotificationSeverity::Success,
        );
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    private function sendAdminInformation(User $user): void
    {
        $recipients = $this->recipients->operational(exclude: $user);
        $subject = $this->translator->trans(
            'user.notification.admin.subject',
            [
                'email' => $user->getEmail(),
            ],
            'emails',
        );
        $content = $this->translator->trans(
            'user.notification.admin.content',
            [
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'id' => $user->getId(),
            ],
            'emails',
        );

        foreach ($recipients as $recipient) {
            $this->notificationSender->send($recipient->getEmail(), $subject, $content);
        }

        $userId = $user->getId();
        $this->inAppNotifications->notifyUsers(
            $recipients,
            $this->translator->trans('notifications.in_app.new_user.admin.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.new_user.admin.body',
                [
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                ],
                'messages',
            ),
            $userId !== null
                ? $this->urlGenerator->generate('app_admin_user_view', [
                    'id' => $userId,
                ]) : $this->urlGenerator->generate('app_admin_users'),
            NotificationSeverity::Info,
        );
    }
}

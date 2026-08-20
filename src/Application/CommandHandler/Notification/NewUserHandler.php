<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\NewUser;
use App\Application\Notification\NotificationSenderInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
readonly class NewUserHandler
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private UserRepositoryInterface $userRepository,
        private TranslatorInterface $translator,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(NewUser $command): void
    {
        // Async transport serializes the User as a detached instance; reload so Doctrine
        // transaction middleware can flush confirmedAt when the handler succeeds.
        $user = $this->userRepository->find($command->user->getId());
        if ($user === null) {
            return;
        }

        // Prevent duplicate email sending if user is already confirmed
        if ($user->getConfirmedAt() !== null) {
            return;
        }

        $user->setConfirmedAt(Clock::get()->now());

        $this->sendUserConfirmation($user);
        $this->sendAdminInformation($user);
    }

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

    private function sendAdminInformation(User $user): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
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

        foreach ($admins as $admin) {
            $this->notificationSender->send($admin->getEmail(), $subject, $content);
        }

        $userId = $user->getId();
        $this->inAppNotifications->notifyAdmins(
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

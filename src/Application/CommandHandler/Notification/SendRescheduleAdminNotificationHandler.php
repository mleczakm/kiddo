<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendRescheduleAdminNotificationCommand;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Entity\NotificationSeverity;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsMessageHandler]
final readonly class SendRescheduleAdminNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UserRepositoryInterface $userRepository,
        private Environment $twig,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(SendRescheduleAdminNotificationCommand $command): void
    {
        $booking = $command->booking;
        $user = $booking->getUser();
        $oldLesson = $command->oldLesson;
        $newLesson = $command->newLesson;

        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        if ($admins === []) {
            return;
        }

        $subject = $this->twig->render('email/notification/reschedule-notification-admin-subject.html.twig', [
            'user' => $user,
            'booking' => $booking,
            'oldLesson' => $oldLesson,
            'newLesson' => $newLesson,
            'reason' => $command->reason,
        ]);

        $content = $this->twig->render('email/notification/reschedule-notification-admin.html.twig', [
            'user' => $user,
            'booking' => $booking,
            'oldLesson' => $oldLesson,
            'newLesson' => $newLesson,
            'reason' => $command->reason,
        ]);

        foreach ($admins as $admin) {
            $notification = new Notification()
                ->importance('')
                ->subject($subject)
                ->content($content);

            $this->notifier->send($notification, new Recipient($admin->getEmailString()));
        }

        $this->inAppNotifications->notifyAdmins(
            $this->translator->trans('notifications.in_app.reschedule.admin.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.reschedule.admin.body',
                [
                    'email' => $user->getEmailString(),
                    'from' => $oldLesson->getMetadata()->title,
                    'to' => $newLesson->getMetadata()->title,
                ],
                'messages',
            ),
            $this->urlGenerator->generate('app_admin_bookings'),
            NotificationSeverity::Info,
        );
    }
}

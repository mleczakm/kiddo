<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendRescheduleAdminNotificationCommand;
use App\Application\Notification\NotificationSenderInterface;
use App\Application\Service\BookingNotificationRecipients;
use App\Application\Service\InAppNotificationService;
use App\Application\Templating\TemplateRendererInterface;
use App\Entity\NotificationSeverity;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class SendRescheduleAdminNotificationHandler
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private BookingNotificationRecipients $recipients,
        private TemplateRendererInterface $templateRenderer,
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

        $recipients = $this->recipients->forLessons([$oldLesson, $newLesson], exclude: $command->rescheduledBy);
        if ($recipients === []) {
            return;
        }

        $subject = $this->templateRenderer->render('email/notification/reschedule-notification-admin-subject.html.twig', [
            'user' => $user,
            'booking' => $booking,
            'oldLesson' => $oldLesson,
            'newLesson' => $newLesson,
            'reason' => $command->reason,
        ]);

        $content = $this->templateRenderer->render('email/notification/reschedule-notification-admin.html.twig', [
            'user' => $user,
            'booking' => $booking,
            'oldLesson' => $oldLesson,
            'newLesson' => $newLesson,
            'reason' => $command->reason,
        ]);

        foreach ($recipients as $recipient) {
            $this->notificationSender->send($recipient->getEmailString(), $subject, $content);
        }

        $this->inAppNotifications->notifyUsers(
            $recipients,
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

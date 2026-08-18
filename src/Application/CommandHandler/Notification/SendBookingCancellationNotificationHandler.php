<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendBookingCancellationNotificationCommand;
use App\Application\Service\InAppNotificationService;
use App\Entity\Booking;
use App\Entity\NotificationSeverity;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class SendBookingCancellationNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private TranslatorInterface $translator,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
    ) {}

    public function __invoke(SendBookingCancellationNotificationCommand $command): void
    {
        $booking = $command->booking;
        $user = $booking->getUser();
        $lessons = $booking->getLessons();

        if (!($firstLesson = $lessons->first())) {
            return;
        }

        /** @var \DateTimeImmutable $lessonDate */
        $lessonDate = $firstLesson->schedule;

        // Get translated content
        $dayOfWeek = $this->translator->trans('from_day_of_week', [
            'day' => (int) $lessonDate->format('N'), // 1 (for Monday) through 7 (for Sunday)
            'date' => $lessonDate->format('d.m'),
            'hour' => $lessonDate->format('H:i'),
        ]);

        $lessonTitle = $firstLesson->getMetadata()->title;

        $subject = $this->translator->trans(
            'booking_cancellation.subject',
            [
                'date' => $dayOfWeek,
                'lesson' => $lessonTitle,
            ],
            'emails',
        );

        $content = $this->translator->trans(
            'booking_cancellation.content',
            [
                'name' => $user->getName(),
                'date' => $dayOfWeek,
                'lesson' => $lessonTitle,
            ],
            'emails',
        );

        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        $recipient = new Recipient($user->getEmailString());

        $this->notifier->send($notification, $recipient);

        $this->inAppNotifications->notify(
            $user,
            $this->translator->trans('notifications.in_app.cancellation.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.cancellation.body',
                [
                    'lesson' => $lessonTitle,
                    'date' => $dayOfWeek,
                ],
                'messages',
            ),
            $this->urlGenerator->generate('dashboard'),
            NotificationSeverity::Warning,
        );

        $this->sendAdminNotifications($booking, $dayOfWeek, $lessonTitle);
    }

    private function sendAdminNotifications(Booking $booking, string $dayOfWeek, string $lessonTitle): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        $user = $booking->getUser();

        foreach ($admins as $admin) {
            $subject = $this->translator->trans(
                'booking_cancellation.admin.subject',
                [
                    'user' => $user->getName(),
                    'date' => $dayOfWeek,
                    'lesson' => $lessonTitle,
                ],
                'emails',
            );

            $content = $this->translator->trans(
                'booking_cancellation.admin.content',
                [
                    'user' => $user->getName(),
                    'email' => $user->getEmail(),
                    'date' => $dayOfWeek,
                    'lesson' => $lessonTitle,
                    'booking_id' => (string) $booking->getId(),
                ],
                'emails',
            );

            $notification = new Notification()
                ->importance('')
                ->subject($subject)
                ->content($content);

            $this->notifier->send($notification, new Recipient($admin->getEmailString()));
        }

        $this->inAppNotifications->notifyAdmins(
            $this->translator->trans('notifications.in_app.cancellation_admin.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.cancellation_admin.body',
                [
                    'email' => $user->getEmail(),
                    'lesson' => $lessonTitle,
                    'date' => $dayOfWeek,
                ],
                'messages',
            ),
            $this->urlGenerator->generate('app_admin_bookings'),
            NotificationSeverity::Warning,
        );
    }
}

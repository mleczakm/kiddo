<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Calendar\LessonCalendarFactory;
use App\Application\Command\Notification\SendPaymentNotificationCommand;
use App\Application\Notification\EmailAttachment;
use App\Application\Notification\NotificationSenderInterface;
use App\Application\Service\BookingNotificationRecipients;
use App\Application\Service\InAppNotificationService;
use App\Application\Templating\TemplateRendererInterface;
use App\Entity\NotificationSeverity;
use App\Entity\Payment;
use App\Entity\User;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
readonly class SendPaymentNotificationHandler
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private BookingNotificationRecipients $recipients,
        private TemplateRendererInterface $templateRenderer,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private LessonCalendarFactory $calendarFactory,
    ) {}

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function __invoke(SendPaymentNotificationCommand $command): void
    {
        $payment = $command->payment;

        $booking = $payment->getBookings()->first();

        if (!$booking) {
            return;
        }

        // Send notification to user who made the payment
        $user = $booking->getUser();
        $this->sendUserNotification($payment, $user);
        // Internal copies go only to finance contacts and the instructors of
        // lessons connected to this payment.
        $this->sendAdminNotifications($payment);
    }

    /**
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    private function sendUserNotification(Payment $payment, User $user): void
    {
        $bookings = $payment->getBookings();
        $firstBooking = $bookings->first() ?: throw new \LogicException('No booking found for payment');
        $lessons = [];
        foreach ($bookings as $booking) {
            foreach ($booking->getLessons() as $lesson) {
                $lessons[] = $lesson;
            }
        }

        $calendarLinks = [];
        foreach ($lessons as $lesson) {
            $calendarLinks[] = $this->calendarFactory->googleCalendarUrl($lesson);
        }

        $subject = $this->templateRenderer->render('email/notification/payment-notification-user-subject.html.twig', [
            'user' => $user,
            'payment' => $payment,
            'reference' => $firstBooking->getId(),
            'lessons' => $lessons,
        ]);
        $content = $this->templateRenderer->render('email/notification/payment-notification-user.html.twig', [
            'user' => $user,
            'payment' => $payment,
            'reference' => $firstBooking->getId(),
            'lessons' => $lessons,
            'bookings' => $bookings,
            'calendarLinks' => $calendarLinks,
        ]);

        $attachments = $lessons === []
            ? []
            : [new EmailAttachment('kalendarz.ics', $this->calendarFactory->icsForLessons($lessons), 'text/calendar')];

        $this->notificationSender->send($user->getEmailString(), $subject, $content, $attachments);

        $lessonTitle = $lessons === [] ? '' : $lessons[0]->getMetadata()->title;
        $this->inAppNotifications->notify(
            $user,
            $this->translator->trans('notifications.in_app.payment.user.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.payment.user.body',
                [
                    'amount' => (string) $payment->getAmount()->getAmount(),
                    'lesson' => $lessonTitle,
                ],
                'messages',
            ),
            $this->urlGenerator->generate('dashboard'),
            NotificationSeverity::Success,
        );
    }

    private function sendAdminNotifications(Payment $payment): void
    {
        $bookings = $payment->getBookings();
        $firstBooking = $bookings->first() ?: throw new \LogicException('No booking found for payment');
        $lessons = [];
        foreach ($bookings as $booking) {
            foreach ($booking->getLessons() as $lesson) {
                $lessons[] = $lesson;
            }
        }
        $recipients = $this->recipients->forLessons($lessons);
        foreach ($recipients as $recipient) {
            $subject = $this->templateRenderer->render('email/notification/payment-notification-admin-subject.html.twig', [
                'user' => $firstBooking->getUser(),
                'payment' => $payment,
                'lessons' => $lessons,
            ]);
            $content = $this->templateRenderer->render('email/notification/payment-notification-admin.html.twig', [
                'user' => $firstBooking->getUser(),
                'payment' => $payment,
                'lessons' => $lessons,
                'bookings' => $bookings,
            ]);
            $this->notificationSender->send($recipient->getEmailString(), $subject, $content);
        }

        $payer = $firstBooking->getUser();
        $lessonTitle = $lessons === [] ? '' : $lessons[0]->getMetadata()->title;
        $this->inAppNotifications->notifyUsers(
            $recipients,
            $this->translator->trans('notifications.in_app.payment.admin.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.payment.admin.body',
                [
                    'email' => $payer->getEmailString(),
                    'amount' => (string) $payment->getAmount()->getAmount(),
                    'lesson' => $lessonTitle,
                ],
                'messages',
            ),
            $this->urlGenerator->generate('app_admin_transfers'),
            NotificationSeverity::Success,
        );
    }
}

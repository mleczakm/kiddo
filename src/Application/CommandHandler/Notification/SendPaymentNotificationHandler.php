<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendPaymentNotificationCommand;
use App\Application\Service\InAppNotificationService;
use App\Entity\NotificationSeverity;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsMessageHandler]
readonly class SendPaymentNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UserRepository $userRepository,
        private Environment $twig,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

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
        // Send notification to all admin users
        $this->sendAdminNotifications($payment);
    }

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

        $subject = $this->twig->render('email/notification/payment-notification-user-subject.html.twig', [
            'user' => $user,
            'payment' => $payment,
            'reference' => $firstBooking->getId(),
            'lessons' => $lessons,
        ]);
        $content = $this->twig->render('email/notification/payment-notification-user.html.twig', [
            'user' => $user,
            'payment' => $payment,
            'reference' => $firstBooking->getId(),
            'lessons' => $lessons,
            'bookings' => $bookings,
        ]);
        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);
        $this->notifier->send($notification, new Recipient($user->getEmailString()));

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
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        $bookings = $payment->getBookings();
        $firstBooking = $bookings->first() ?: throw new \LogicException('No booking found for payment');
        $lessons = [];
        foreach ($bookings as $booking) {
            foreach ($booking->getLessons() as $lesson) {
                $lessons[] = $lesson;
            }
        }
        foreach ($admins as $admin) {
            $subject = $this->twig->render('email/notification/payment-notification-admin-subject.html.twig', [
                'user' => $firstBooking->getUser(),
                'payment' => $payment,
                'lessons' => $lessons,
            ]);
            $content = $this->twig->render('email/notification/payment-notification-admin.html.twig', [
                'user' => $firstBooking->getUser(),
                'payment' => $payment,
                'lessons' => $lessons,
                'bookings' => $bookings,
            ]);
            $notification = new Notification()
                ->importance('')
                ->subject($subject)
                ->content($content);
            $this->notifier->send($notification, new Recipient($admin->getEmailString()));
        }

        $payer = $firstBooking->getUser();
        $lessonTitle = $lessons === [] ? '' : $lessons[0]->getMetadata()->title;
        $this->inAppNotifications->notifyAdmins(
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

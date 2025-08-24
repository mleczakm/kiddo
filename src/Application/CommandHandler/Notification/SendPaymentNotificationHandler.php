<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendPaymentNotificationCommand;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Twig\Environment;

#[AsMessageHandler]
readonly class SendPaymentNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UserRepository $userRepository,
        private Environment $twig,
    ) {}

    public function __invoke(SendPaymentNotificationCommand $command): void
    {
        $payment = $command->payment;

        $booking = $payment->getBookings()
            ->first();

        if (! $booking) {
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
        ]);
        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);
        $this->notifier->send($notification, new Recipient($user->getEmailString()));
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
                'user' => $admin,
                'payment' => $payment,
                'lessons' => $lessons,
            ]);
            $notification = new Notification()
                ->importance('')
                ->subject($subject)
                ->content($content);
            $this->notifier->send($notification, new Recipient($admin->getEmailString()));
        }
    }
}

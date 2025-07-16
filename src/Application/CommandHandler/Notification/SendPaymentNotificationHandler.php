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
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
readonly class SendPaymentNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UserRepository $userRepository,
        private TranslatorInterface $translator,
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
        $lessonsDetails = [];
        $firstBooking = $bookings->first() ?: throw new \LogicException('No booking found for payment');

        foreach ($bookings as $booking) {
            foreach ($booking->getLessons() as $lesson) {
                $schedule = $lesson->getMetadata()
                    ->schedule;
                $lessonsDetails[] = [
                    'title' => $lesson->getMetadata()
                        ->title,
                    'date' => $schedule->format('Y-m-d'),
                    'time' => $schedule->format('H:i'),
                ];
            }
        }

        $translatorContext = [
            'amount' => (string) $payment->getAmount(),
            'reference' => (string) $firstBooking->getId(),
            'date' => $payment->getCreatedAt()
                ->format('Y-m-d H:i'),
            'lessons' => $this->formatLessonsList($lessonsDetails),
        ];

        $subject = $this->translator->trans('payment.notification.user.subject', [], 'emails');
        $content = $this->translator->trans('payment.notification.user.message', $translatorContext, 'emails');

        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        $this->notifier->send($notification, new Recipient($user->getEmail()));
    }

    /**
     * @param list<array{title: string, date: string, time: string}> $lessons
     */
    private function formatLessonsList(array $lessons): string
    {
        $formatted = [];
        foreach ($lessons as $lesson) {
            $formatted[] = sprintf('- %s, %s %s', $lesson['title'], $lesson['date'], $lesson['time']);
        }
        return implode("\n", $formatted);
    }

    private function sendAdminNotifications(Payment $payment): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        $bookings = $payment->getBookings();
        $firstBooking = $bookings->first() ?: throw new \LogicException('No booking found for payment');
        $lessonsDetails = [];

        foreach ($bookings as $booking) {
            foreach ($booking->getLessons() as $lesson) {
                $schedule = $lesson->getMetadata()
                    ->schedule;
                $lessonsDetails[] = [
                    'title' => $lesson->getMetadata()
                        ->title,
                    'date' => $schedule->format('Y-m-d'),
                    'time' => $schedule->format('H:i'),
                ];
            }
        }

        $translatorContext = [
            'id' => (string) $payment->getId(),
            'amount' => (string) $payment->getAmount(),
            'user' => $firstBooking->getUser()
                ->getEmail(),
            'booking' => $firstBooking->getId(),
            'lessons' => $this->formatLessonsList($lessonsDetails),
        ];

        $subject = $this->translator->trans('payment.notification.admin.subject', [
            'id' => $payment->getId(),
        ], 'emails');
        $content = $this->translator->trans('payment.notification.admin.greeting', $translatorContext, 'emails');

        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        foreach ($admins as $admin) {
            $this->notifier->send($notification, new Recipient($admin->getEmail()));
        }
    }
}

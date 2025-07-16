<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendBookingCancellationNotificationCommand;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class SendBookingCancellationNotificationHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(SendBookingCancellationNotificationCommand $command): void
    {
        $booking = $command->booking;
        $user = $booking->getUser();
        $lessons = $booking->getLessons();

        if (! $firstLesson = $lessons->first()) {
            return;
        }

        /** @var \DateTimeImmutable $lessonDate */
        $lessonDate = $firstLesson->getMetadata()
            ->schedule;

        // Get translated content
        $dayOfWeek = $this->translator->trans('from_day_of_week', [
            'day' => (int) $lessonDate->format('N'), // 1 (for Monday) through 7 (for Sunday)
            'date' => $lessonDate->format('d.m'),
            'hour' => $lessonDate->format('H:i'),
        ]);

        $subject = $this->translator->trans('booking_cancellation.subject', [
            'date' => $dayOfWeek,
            'lesson' => $firstLesson->getMetadata()
                ->title,
        ], 'emails');

        $content = $this->translator->trans('booking_cancellation.content', [
            'name' => $user->getName(),
            'date' => $dayOfWeek,
            'lesson' => $firstLesson->getMetadata()
                ->title,
        ], 'emails');

        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        $recipient = new Recipient($user->getEmailString());

        $this->notifier->send($notification, $recipient);
    }
}

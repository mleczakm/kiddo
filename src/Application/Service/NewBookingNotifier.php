<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Booking;
use App\Entity\NotificationSeverity;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class NewBookingNotifier
{
    public function __construct(
        private BookingNotificationRecipients $recipients,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {}

    public function notify(Booking $booking): void
    {
        $customer = $booking->getUser();
        foreach ($booking->getLessons() as $lesson) {
            try {
                $this->inAppNotifications->notifyUsers(
                    $this->recipients->forLessons([$lesson], exclude: $customer),
                    $this->translator->trans('notifications.in_app.new_booking.internal.title', [], 'messages'),
                    $this->translator->trans(
                        'notifications.in_app.new_booking.internal.body',
                        [
                            'name' => $customer->getName(),
                            'lesson' => $lesson->getMetadata()->title,
                            'date' => $lesson->schedule->format('Y-m-d H:i'),
                        ],
                        'messages',
                    ),
                    $this->urlGenerator->generate('app_admin_lesson_view', [
                        'id' => (string) $lesson->getId(),
                    ]),
                    NotificationSeverity::Info,
                );
            } catch (\InvalidArgumentException|RoutingExceptionInterface $exception) {
                $this->logger->error('Unable to build new-booking staff notification.', [
                    'booking_id' => (string) $booking->getId(),
                    'lesson_id' => (string) $lesson->getId(),
                    'exception' => $exception,
                ]);
            }
        }
    }
}

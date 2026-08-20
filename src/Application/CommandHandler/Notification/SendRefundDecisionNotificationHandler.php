<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\SendRefundDecisionNotificationCommand;
use App\Application\Notification\NotificationSenderInterface;
use App\Application\Repository\RefundRequestRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Entity\NotificationSeverity;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class SendRefundDecisionNotificationHandler
{
    public function __construct(
        private RefundRequestRepositoryInterface $refundRequestRepository,
        private NotificationSenderInterface $notificationSender,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(SendRefundDecisionNotificationCommand $command): void
    {
        $refundRequest = $this->refundRequestRepository->find($command->refundRequestId);
        if ($refundRequest === null) {
            return;
        }

        $user = $refundRequest->getBooking()->getUser();
        $lessonTitle = $refundRequest->getLesson()?->getMetadata()->title ?? '';
        $key = $command->approved ? 'approved' : 'declined';

        $subject = $this->translator->trans(
            sprintf('notifications.in_app.refund_decision.%s.title', $key),
            [],
            'messages',
        );
        $body = $this->translator->trans(
            sprintf('notifications.in_app.refund_decision.%s.body', $key),
            [
                'amount' => (string) $refundRequest->getRequestedAmount()->getAmount(),
                'lesson' => $lessonTitle,
            ],
            'messages',
        );

        $this->notificationSender->send($user->getEmailString(), $subject, $body);

        $this->inAppNotifications->notify(
            $user,
            $subject,
            $body,
            $this->urlGenerator->generate('dashboard'),
            $command->approved ? NotificationSeverity::Success : NotificationSeverity::Warning,
        );
    }
}

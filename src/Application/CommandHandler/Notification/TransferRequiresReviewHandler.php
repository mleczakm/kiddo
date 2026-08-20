<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\TransferRequiresReviewCommand;
use App\Application\Notification\NotificationSenderInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Entity\NotificationSeverity;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class TransferRequiresReviewHandler
{
    private const string CACHE_PREFIX = 'transfer_review_notification_';

    private const int CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private UserRepositoryInterface $userRepository,
        private TranslatorInterface $translator,
        private CacheItemPoolInterface $cache,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @throws \LogicException When the transfer has not been persisted yet.
     */
    public function __invoke(TransferRequiresReviewCommand $command): void
    {
        $transfer = $command->transfer;
        $transferId = $transfer->getId();
        if ($transferId === null) {
            throw new \LogicException('Cannot notify about a transfer that has not been persisted.');
        }

        $cacheKey = self::CACHE_PREFIX . $transferId;

        try {
            if ($this->cache->hasItem($cacheKey)) {
                return;
            }

            $admins = $this->userRepository->findByRole('ROLE_ADMIN');
            if ($admins === []) {
                return;
            }

            $subject = $this->translator->trans('transfer.notification.requires_review.subject', [], 'emails');
            $content = $this->translator->trans(
                'transfer.notification.requires_review.content',
                [
                    'amount' => $transfer->amount,
                    'sender' => $transfer->getSender(),
                    'title' => $transfer->title,
                    'date' => $transfer->getTransferredAt()->format('Y-m-d H:i'),
                ],
                'emails',
            );

            foreach ($admins as $admin) {
                $this->notificationSender->send($admin->getEmailString(), $subject, $content);
            }

            $this->inAppNotifications->notifyAdmins(
                $this->translator->trans('notifications.in_app.transfer_requires_review.title', [], 'messages'),
                $this->translator->trans(
                    'notifications.in_app.transfer_requires_review.body',
                    [
                        'amount' => $transfer->amount,
                        'sender' => $transfer->getSender(),
                        'title' => $transfer->title,
                    ],
                    'messages',
                ),
                $this->urlGenerator->generate('app_admin_transfers'),
                NotificationSeverity::Warning,
            );
        } finally {
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set(true);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);
        }
    }
}

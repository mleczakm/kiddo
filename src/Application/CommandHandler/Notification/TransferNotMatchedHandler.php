<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Application\Repository\PaymentRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Entity\NotificationSeverity;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class TransferNotMatchedHandler
{
    private const string CACHE_PREFIX = 'transfer_notification_';

    private const int CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function __construct(
        private NotifierInterface $notifier,
        private UserRepositoryInterface $userRepository,
        private TranslatorInterface $translator,
        private CacheItemPoolInterface $cache,
        private PaymentRepositoryInterface $paymentRepository,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @throws \LogicException When the transfer has not been persisted yet.
     */
    public function __invoke(TransferNotMatchedCommand $command): void
    {
        $transfer = $command->transfer;
        $transferId = $transfer->getId();
        if ($transferId === null) {
            throw new \LogicException('Cannot notify about a transfer that has not been persisted.');
        }

        $cacheKey = self::CACHE_PREFIX . $transferId;

        try {
            // Check if we've already sent a notification for this transfer today
            if ($this->cache->hasItem($cacheKey)) {
                return;
            }

            if ($this->paymentRepository->countPendingPayments() === 0) {
                return;
            }

            // Get all admin users
            $admins = $this->userRepository->findByRole('ROLE_ADMIN');
            if (empty($admins)) {
                return;
            }

            // Prepare notification
            $subject = $this->translator->trans('transfer.notification.not_matched.subject', [], 'emails');
            $content = $this->translator->trans(
                'transfer.notification.not_matched.content',
                [
                    'amount' => $transfer->amount,
                    'sender' => $transfer->getSender(),
                    'title' => $transfer->title,
                    'date' => $transfer->getTransferredAt()->format('Y-m-d H:i'),
                ],
                'emails',
            );

            $notification = new Notification()
                ->importance('')
                ->subject($subject)
                ->content($content);

            // Send it to all admins
            foreach ($admins as $admin) {
                $this->notifier->send($notification, new Recipient($admin->getEmailString()));
            }

            $this->inAppNotifications->notifyAdmins(
                $this->translator->trans('notifications.in_app.transfer_not_matched.title', [], 'messages'),
                $this->translator->trans(
                    'notifications.in_app.transfer_not_matched.body',
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
            // Cache the notification for today
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set(true);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);
        }
    }
}

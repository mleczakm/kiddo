<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush;

use App\Application\Notification\WebPushSenderInterface;
use App\Application\Repository\PushSubscriptionRepositoryInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class WebPushSender implements WebPushSenderInterface
{
    /**
     * @param array{subject: string, publicKey: string, privateKey: string} $vapid
     */
    public function __construct(
        private ClientInterface $httpClient,
        #[\SensitiveParameter]
        #[Autowire('%vapid.config%')]
        private array $vapid,
        private PushSubscriptionRepositoryInterface $subscriptions,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function sendToUser(User $user, string $title, ?string $body, ?string $url): void
    {
        if ($this->vapid['publicKey'] === '' || $this->vapid['privateKey'] === '') {
            return;
        }

        $subscriptions = $this->subscriptions->findByUser($user);
        if ($subscriptions === []) {
            return;
        }

        $webPush = new WebPush(['VAPID' => $this->vapid], client: $this->httpClient);

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->getEndpoint(),
                'keys' => [
                    'p256dh' => $subscription->getP256dh(),
                    'auth' => $subscription->getAuth(),
                ],
            ]), $payload);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            if ($report->isSubscriptionExpired()) {
                $this->removeByEndpoint($report->getEndpoint());

                continue;
            }

            $this->logger->warning('Web push delivery failed', [
                'endpoint' => $report->getEndpoint(),
                'reason' => $report->getReason(),
            ]);
        }
    }

    private function removeByEndpoint(string $endpoint): void
    {
        $subscription = $this->subscriptions->findByEndpoint($endpoint);
        if ($subscription === null) {
            return;
        }

        $this->em->remove($subscription);
        $this->em->flush();
    }
}

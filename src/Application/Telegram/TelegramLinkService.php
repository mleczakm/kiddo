<?php

declare(strict_types=1);

namespace App\Application\Telegram;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class TelegramLinkService implements TelegramLinkServiceInterface
{
    private const string CACHE_PREFIX = 'telegram_link_';

    private const int TTL_SECONDS = 900;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
    ) {}

    public function startLink(string $telegramChatId, string $email): void
    {
        $user = $this->userRepository->findOneBy([
            'email' => mb_strtolower(trim($email)),
        ]);
        if (! $user instanceof User) {
            // Anti-enumeration: still pretend success without revealing missing accounts.
            return;
        }

        $code = (string) random_int(100000, 999999);
        $item = $this->cache->getItem(self::CACHE_PREFIX . $telegramChatId);
        $item->set([
            'user_id' => $user->getId(),
            'code' => $code,
        ]);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);

        $emailMessage = new Email()
            ->to($user->getEmail())
            ->subject('Kod połączenia Telegram — Kiddo')
            ->text(sprintf(
                "Twój kod połączenia konta z Telegramem: %s\nWpisz w Telegramie: /kod %s\nKod ważny 15 minut.",
                $code,
                $code
            ));
        $this->mailer->send($emailMessage);
    }

    public function confirmLink(string $telegramChatId, string $code): ?User
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX . $telegramChatId);
        if (! $item->isHit()) {
            return null;
        }
        /** @var array{user_id: int, code: string} $payload */
        $payload = $item->get();
        if (! hash_equals((string) $payload['code'], trim($code))) {
            return null;
        }

        $user = $this->userRepository->find((int) $payload['user_id']);
        if (! $user instanceof User) {
            return null;
        }

        $existing = $this->userRepository->findOneBy([
            'telegramChatId' => $telegramChatId,
        ]);
        if ($existing instanceof User && $existing->getId() !== $user->getId()) {
            $existing->setTelegramChatId(null);
        }

        $user->setTelegramChatId($telegramChatId);
        $this->entityManager->flush();
        $this->cache->deleteItem(self::CACHE_PREFIX . $telegramChatId);

        return $user;
    }

    public function findLinkedUser(string $telegramChatId): ?User
    {
        $user = $this->userRepository->findOneBy([
            'telegramChatId' => $telegramChatId,
        ]);

        return $user instanceof User ? $user : null;
    }

    public function unlink(User $user): void
    {
        $user->setTelegramChatId(null);
        $this->entityManager->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\Notification\SendVerificationCode;
use App\Application\Repository\UserRepositoryInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AuthVerificationService
{
    private const int CODE_TTL_SECONDS = 600;

    public function __construct(
        private CacheItemPoolInterface $cache,
        private MessageBusInterface $messageBus,
        private UserRepositoryInterface $userRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @throws \Random\RandomException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function issue(string $email): void
    {
        $code = sprintf('%06d', random_int(0, 999_999));
        $item = $this->cache->getItem($this->cacheKey($email));
        $item->set($code);
        $item->expiresAfter(self::CODE_TTL_SECONDS);
        $this->cache->save($item);

        $this->messageBus->dispatch(new SendVerificationCode($email, $code));
    }

    /**
     * Validates and consumes a single-use code, confirming the account when necessary.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function consume(string $email, string $code): ?User
    {
        $cacheKey = $this->cacheKey($email);
        $item = $this->cache->getItem($cacheKey);
        $storedCode = $this->stringValue($item);
        if (!$item->isHit() || !is_string($storedCode) || !hash_equals($storedCode, $code)) {
            return null;
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);
        if ($user === null) {
            return null;
        }

        $this->cache->deleteItem($cacheKey);
        if ($user->getConfirmedAt() === null) {
            $user->setConfirmedAt(Clock::get()->now());
            $this->entityManager->flush();
        }

        return $user;
    }

    private function cacheKey(string $email): string
    {
        return sprintf('verification_code_%s', hash('xxh3', mb_strtolower($email)));
    }

    private function stringValue(CacheItemInterface $item): ?string
    {
        return is_string($item->get()) ? $item->get() : null;
    }
}

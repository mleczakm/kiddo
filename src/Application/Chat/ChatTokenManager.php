<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ChatTokenManager
{
    private const int DEFAULT_TTL_SECONDS = 3600;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {}

    public function mint(User $user, ?int $ttlSeconds = null): string
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new \InvalidArgumentException('Cannot mint chat token for unsaved user');
        }

        return $this->encode([
            'uid' => $userId,
            'roles' => $user->getRoles(),
            'exp' => time() + ($ttlSeconds ?? self::DEFAULT_TTL_SECONDS),
            'jti' => bin2hex(random_bytes(16)),
        ]);
    }

    public function mintGuest(?int $ttlSeconds = null): string
    {
        return $this->encode([
            'uid' => null,
            'guest' => true,
            'roles' => [],
            'exp' => time() + ($ttlSeconds ?? self::DEFAULT_TTL_SECONDS),
            'jti' => bin2hex(random_bytes(16)),
        ]);
    }

    public function parse(string $token): ChatToken
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Malformed chat token');
        }

        [$body, $signature] = $parts;
        if (! hash_equals($this->sign($body), $signature)) {
            throw new \InvalidArgumentException('Invalid chat token signature');
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if ($json === false) {
            throw new \InvalidArgumentException('Invalid chat token encoding');
        }

        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)
            || ! isset($decoded['roles'], $decoded['exp'], $decoded['jti'])
            || ! is_array($decoded['roles'])
        ) {
            throw new \InvalidArgumentException('Invalid chat token payload');
        }

        /** @var list<string> $roles */
        $roles = array_values(array_filter($decoded['roles'], is_string(...)));

        $uidRaw = $decoded['uid'] ?? null;
        $isGuest = ($decoded['guest'] ?? false) === true
            || $uidRaw === null
            || $uidRaw === 0;
        $userId = $isGuest ? null : (int) $uidRaw;

        $chatToken = new ChatToken(
            userId: $userId,
            roles: $roles,
            expiresAt: new \DateTimeImmutable()
                ->setTimestamp((int) $decoded['exp']),
            jti: (string) $decoded['jti'],
        );

        if ($chatToken->isExpired()) {
            throw new \InvalidArgumentException('Chat token expired');
        }

        return $chatToken;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature = $this->sign($body);

        return $body . '.' . $signature;
    }

    private function sign(string $body): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $this->secret, true)), '+/', '-_'), '=');
    }
}

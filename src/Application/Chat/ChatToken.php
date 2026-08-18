<?php

declare(strict_types=1);

namespace App\Application\Chat;

final readonly class ChatToken
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public ?int $userId,
        public array $roles,
        public \DateTimeImmutable $expiresAt,
        public string $jti,
    ) {}

    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->expiresAt <= $now;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true) || in_array('ROLE_SUPER_ADMIN', $this->roles, true);
    }
}

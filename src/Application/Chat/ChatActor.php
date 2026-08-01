<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Entity\User;

final readonly class ChatActor
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public User $user,
        public array $roles,
    ) {}

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true)
            || in_array('ROLE_SUPER_ADMIN', $this->roles, true);
    }

    public function userId(): int
    {
        $id = $this->user->getId();
        if ($id === null) {
            throw new \LogicException('Chat actor user must be persisted');
        }

        return $id;
    }
}

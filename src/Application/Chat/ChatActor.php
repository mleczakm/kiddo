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
        public ?User $user,
        public array $roles,
    ) {}

    public static function guest(): self
    {
        return new self(null, []);
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function requireUser(): User
    {
        if ($this->user === null) {
            throw new \LogicException('This action requires a logged-in user');
        }

        return $this->user;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true)
            || in_array('ROLE_SUPER_ADMIN', $this->roles, true);
    }

    public function userId(): int
    {
        $id = $this->requireUser()
            ->getId();
        if ($id === null) {
            throw new \LogicException('Chat actor user must be persisted');
        }

        return $id;
    }
}

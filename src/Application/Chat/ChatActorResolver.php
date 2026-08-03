<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Entity\User;
use App\Repository\UserRepository;

final readonly class ChatActorResolver
{
    public function __construct(
        private ChatTokenManager $tokenManager,
        private UserRepository $userRepository,
    ) {}

    public function fromTokenString(string $token): ChatActor
    {
        $chatToken = $this->tokenManager->parse($token);
        if ($chatToken->isGuest()) {
            return ChatActor::guest();
        }

        $user = $this->userRepository->find($chatToken->userId);
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('Chat token user not found');
        }

        $roles = $chatToken->roles !== [] ? $chatToken->roles : $user->getRoles();

        return new ChatActor($user, array_values($roles));
    }

    public function fromUser(User $user): ChatActor
    {
        return new ChatActor($user, array_values($user->getRoles()));
    }
}

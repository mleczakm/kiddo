<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Repository\UserRepositoryInterface;
use App\Entity\User;

final readonly class ChatActorResolver
{
    public function __construct(
        private ChatTokenManager $tokenManager,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function fromTokenString(#[\SensitiveParameter] string $token): ChatActor
    {
        $chatToken = $this->tokenManager->parse($token);
        if ($chatToken->isGuest()) {
            return ChatActor::guest();
        }

        $user = $this->userRepository->find($chatToken->userId);
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Chat token user not found');
        }

        $roles = $chatToken->roles !== [] ? $chatToken->roles : $user->getRoles();

        return new ChatActor($user, $roles);
    }

    public function fromUser(User $user): ChatActor
    {
        return new ChatActor($user, $user->getRoles());
    }
}

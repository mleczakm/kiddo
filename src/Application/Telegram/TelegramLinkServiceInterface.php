<?php

declare(strict_types=1);

namespace App\Application\Telegram;

use App\Entity\User;

interface TelegramLinkServiceInterface
{
    public function startLink(string $telegramChatId, string $email): void;

    public function confirmLink(string $telegramChatId, string $code): ?User;

    public function findLinkedUser(string $telegramChatId): ?User;

    public function unlink(User $user): void;
}

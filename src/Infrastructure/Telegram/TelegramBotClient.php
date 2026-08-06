<?php

declare(strict_types=1);

namespace App\Infrastructure\Telegram;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TelegramBotClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(TELEGRAM_BOT_TOKEN)%')]
        private string $botToken,
    ) {}

    public function isConfigured(): bool
    {
        return $this->botToken !== '';
    }

    public function sendMessage(string $chatId, string $text): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('TELEGRAM_BOT_TOKEN is not configured');
        }

        $this->httpClient->request('POST', sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken), [
            'json' => [
                'chat_id' => $chatId,
                'text' => mb_substr($text, 0, 4000),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Telegram\TelegramCommandRouter;
use App\Infrastructure\Telegram\TelegramBotClient;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TelegramWebhookAction extends AbstractController
{
    public function __construct(
        private readonly TelegramCommandRouter $router,
        private readonly TelegramBotClient $botClient,
        private readonly FeatureManager $featureManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(TELEGRAM_WEBHOOK_SECRET)%')]
        private readonly string $webhookSecret,
    ) {}

    #[Route('/api/telegram/webhook', name: 'api_telegram_webhook', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->featureManager->isEnabled('chat_assistant')) {
            return $this->json([
                'ok' => false,
            ], Response::HTTP_NOT_FOUND);
        }

        if ($this->webhookSecret !== '') {
            $provided = $request->headers->get('X-Telegram-Bot-Api-Secret-Token');
            if (! is_string($provided) || ! hash_equals($this->webhookSecret, $provided)) {
                return $this->json([
                    'ok' => false,
                ], Response::HTTP_UNAUTHORIZED);
            }
        }

        $payload = json_decode($request->getContent() ?: '{}', true);
        if (! is_array($payload)) {
            return $this->json([
                'ok' => true,
            ]);
        }

        $message = $payload['message'] ?? $payload['edited_message'] ?? null;
        if (! is_array($message)) {
            return $this->json([
                'ok' => true,
            ]);
        }

        $chat = $message['chat'] ?? null;
        $text = $message['text'] ?? null;
        if (! is_array($chat) || ! isset($chat['id']) || ! is_string($text)) {
            return $this->json([
                'ok' => true,
            ]);
        }

        $chatIdRaw = $chat['id'];
        if (! is_string($chatIdRaw) && ! is_int($chatIdRaw)) {
            return $this->json([
                'ok' => true,
            ]);
        }
        $chatId = (string) $chatIdRaw;

        try {
            $reply = $this->router->handle($chatId, $text);
            if ($this->botClient->isConfigured()) {
                $this->botClient->sendMessage($chatId, $reply);
            } else {
                $this->logger->warning('Telegram bot token missing; computed reply only', [
                    'chat_id' => $chatId,
                    'reply' => $reply,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Telegram webhook failed', [
                'exception' => $e,
            ]);
            if ($this->botClient->isConfigured()) {
                $this->botClient->sendMessage($chatId, 'Wystąpił błąd. Spróbuj ponownie.');
            }
        }

        return $this->json([
            'ok' => true,
        ]);
    }
}

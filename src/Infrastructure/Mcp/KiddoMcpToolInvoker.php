<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatActorResolver;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\ToolResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Executes Kiddo chat tools for MCP handlers.
 *
 * Identity: conversation-scoped chat token (`X-Kiddo-Chat-Token` or tool arg).
 * A missing/expired/invalid token degrades to a guest actor — never a thrown
 * exception, which the MCP transport would surface to ElevenLabs as an opaque
 * "Failed to execute MCP tool" and fail the whole turn. `ChatToolRegistry`
 * still gates auth-only tools and returns a friendly login prompt.
 * Do not reuse `Authorization` — that header carries the MCP service key for ElevenLabs.
 */
final readonly class KiddoMcpToolInvoker
{
    /**
     * ElevenLabs forwards the dynamic-variable reference verbatim when it is unset.
     */
    private const string UNRESOLVED_TOKEN_PLACEHOLDER = '{{kiddo_chat_token}}';

    public function __construct(
        private ChatToolRegistry $registry,
        private ChatActorResolver $actorResolver,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function invoke(string $toolName, array $arguments): array
    {
        unset($arguments['_session'], $arguments['_request']);

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new \RuntimeException('MCP tool call requires an HTTP request context');
        }

        $token = $this->resolveChatToken($request, $arguments);
        unset($arguments['kiddo_chat_token'], $arguments['chat_token']);

        try {
            $actor = $token !== null ? $this->actorResolver->fromTokenString($token) : ChatActor::guest();
        } catch (\InvalidArgumentException|\JsonException $e) {
            $this->logger->warning('MCP chat token rejected', [
                'tool' => $toolName,
                'reason' => $e->getMessage(),
            ]);

            return ToolResult::failure(
                'Chat session expired or invalid. Ask the user to refresh the chat, and to log in if they need account data.',
                'Sesja czatu wygasła lub jest nieprawidłowa. Odśwież czat, a jeśli potrzebujesz danych konta — zaloguj się ponownie i odśwież.',
            )->toArray();
        }

        try {
            $result = $this->registry->call($toolName, $actor, $arguments);
        } catch (\Throwable $e) {
            $this->logger->error('MCP tool execution failed', [
                'tool' => $toolName,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ToolResult::failure(
                sprintf('Tool "%s" failed: %s', $toolName, $e->getMessage()),
                'Wystąpił chwilowy błąd po stronie systemu. Spróbuj ponownie za chwilę.',
            )->toArray();
        }

        $this->logger->info('MCP tool invoked', [
            'tool' => $toolName,
            'user_id' => $actor->isGuest() ? null : $actor->userId(),
            'guest' => $actor->isGuest(),
            'ok' => $result->ok,
            'args_hash' => hash('xxh3', json_encode($arguments, JSON_THROW_ON_ERROR)),
        ]);

        return $result->toArray();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function resolveChatToken(Request $request, array $arguments): ?string
    {
        $candidates = [
            $request->headers->get('X-Kiddo-Chat-Token'),
            $arguments['kiddo_chat_token'] ?? null,
            $arguments['chat_token'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $candidate = trim($candidate);
            if ($candidate === '' || $candidate === self::UNRESOLVED_TOKEN_PLACEHOLDER) {
                continue;
            }

            return $candidate;
        }

        return null;
    }
}

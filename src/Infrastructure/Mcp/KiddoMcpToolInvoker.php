<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Application\Chat\ChatActorResolver;
use App\Application\Chat\ChatToolRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Executes Kiddo chat tools for MCP handlers.
 *
 * Identity: conversation-scoped chat token only (`X-Kiddo-Chat-Token` or tool arg).
 * Do not reuse `Authorization` — that header carries the MCP service key for ElevenLabs.
 */
final readonly class KiddoMcpToolInvoker
{
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

        $actor = $this->actorResolver->fromTokenString($token);
        $result = $this->registry->call($toolName, $actor, $arguments);

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
    private function resolveChatToken(Request $request, array $arguments): string
    {
        $candidates = [
            $request->headers->get('X-Kiddo-Chat-Token'),
            $arguments['kiddo_chat_token'] ?? null,
            $arguments['chat_token'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException(
            'Missing chat identity. Configure MCP request header X-Kiddo-Chat-Token={{kiddo_chat_token}} (dynamic variable from signed-url).',
        );
    }
}

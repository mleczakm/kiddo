<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Application\Chat\ChatActorResolver;
use App\Application\Chat\ChatToolRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Executes Kiddo chat tools for MCP handlers (auth via chat token headers).
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

        $token = $request->headers->get('X-Kiddo-Chat-Token');
        if (! is_string($token) || $token === '') {
            $auth = $request->headers->get('Authorization');
            if (is_string($auth) && str_starts_with($auth, 'Bearer ')) {
                $token = substr($auth, 7);
            }
        }
        if (! is_string($token) || $token === '') {
            throw new \InvalidArgumentException('Missing X-Kiddo-Chat-Token or Authorization Bearer chat token');
        }

        $actor = $this->actorResolver->fromTokenString($token);
        $result = $this->registry->call($toolName, $actor, $arguments);

        $this->logger->info('MCP tool invoked', [
            'tool' => $toolName,
            'user_id' => $actor->userId(),
            'ok' => $result->ok,
            'args_hash' => hash('xxh3', json_encode($arguments, JSON_THROW_ON_ERROR)),
        ]);

        return $result->toArray();
    }
}

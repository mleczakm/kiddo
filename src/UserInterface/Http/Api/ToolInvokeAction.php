<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatActorResolver;
use App\Application\Chat\ChatToolRegistry;
use App\Entity\User;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ToolInvokeAction extends AbstractController
{
    public function __construct(
        private readonly ChatToolRegistry $registry,
        private readonly ChatActorResolver $actorResolver,
        private readonly FeatureManager $featureManager,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/api/v1/tools', name: 'api_v1_tools_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (! $this->featureManager->isEnabled('chat_assistant')) {
            return $this->json([
                'error' => 'Chat assistant is disabled',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $actor = $this->resolveActor($request);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $tools = array_map(
            static fn($d) => [
                'name' => $d->name,
                'description' => $d->description,
                'input_schema' => $d->inputSchema,
                'requires_admin' => $d->requiresAdmin,
                'requires_confirm' => $d->requiresConfirm,
            ],
            $this->registry->definitions($actor)
        );

        return $this->json([
            'tools' => $tools,
        ]);
    }

    #[Route('/api/v1/tools/{name}', name: 'api_v1_tools_invoke', methods: ['POST'], requirements: [
        'name' => '.+',
    ])]
    public function invoke(string $name, Request $request): JsonResponse
    {
        if (! $this->featureManager->isEnabled('chat_assistant')) {
            return $this->json([
                'error' => 'Chat assistant is disabled',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $actor = $this->resolveActor($request);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $decoded = json_decode($request->getContent() ?: '{}', true);
        if (! is_array($decoded)) {
            return $this->json([
                'error' => 'Invalid JSON body',
            ], Response::HTTP_BAD_REQUEST);
        }
        /** @var array<string, mixed> $arguments */
        $arguments = $decoded;

        $result = $this->registry->call($name, $actor, $arguments);
        $this->logger->info('Chat tool invoked', [
            'tool' => $name,
            'user_id' => $actor->userId(),
            'ok' => $result->ok,
            'args_hash' => hash('xxh3', json_encode($arguments, JSON_THROW_ON_ERROR)),
            'error' => $result->error,
        ]);

        return $this->json($result->toArray(), $result->ok ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function resolveActor(Request $request): ChatActor
    {
        $bearer = $this->extractBearer($request);
        if ($bearer !== null) {
            return $this->actorResolver->fromTokenString($bearer);
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->actorResolver->fromUser($user);
        }

        throw new \InvalidArgumentException('Missing Authorization Bearer chat token or session');
    }

    private function extractBearer(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        $chatToken = $request->headers->get('X-Kiddo-Chat-Token');

        return is_string($chatToken) && $chatToken !== '' ? $chatToken : null;
    }
}

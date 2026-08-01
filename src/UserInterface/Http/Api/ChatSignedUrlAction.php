<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use App\Application\Chat\ChatTokenManager;
use App\Entity\User;
use App\Infrastructure\ElevenLabs\ElevenLabsClient;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ChatSignedUrlAction extends AbstractController
{
    public function __construct(
        private readonly ElevenLabsClient $elevenLabsClient,
        private readonly ChatTokenManager $chatTokenManager,
        private readonly FeatureManager $featureManager,
    ) {}

    #[Route('/api/chat/signed-url', name: 'api_chat_signed_url', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->featureManager->isEnabled('chat_assistant')) {
            return $this->json([
                'error' => 'Chat assistant is disabled',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        if (! $user instanceof User) {
            return $this->json([
                'error' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array{admin?: bool} $payload */
        $payload = json_decode($request->getContent() ?: '{}', true) ?? [];
        $wantAdmin = (bool) ($payload['admin'] ?? false);
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if ($wantAdmin && ! $isAdmin) {
            return $this->json([
                'error' => 'Admin agent requires ROLE_ADMIN',
            ], Response::HTTP_FORBIDDEN);
        }

        $chatToken = $this->chatTokenManager->mint($user);
        $dynamicVariables = [
            'kiddo_user_id' => (string) $user->getId(),
            'kiddo_user_name' => $user->getName(),
            'kiddo_user_email' => $user->getEmail(),
            'kiddo_roles' => implode(',', $user->getRoles()),
            'kiddo_chat_token' => $chatToken,
            'kiddo_is_admin' => $isAdmin ? 'true' : 'false',
        ];

        if (! $this->elevenLabsClient->isConfigured()) {
            // Dev / test fallback: return token + mock WS placeholder so UI can still boot.
            return $this->json([
                'signed_url' => null,
                'agent_id' => null,
                'chat_token' => $chatToken,
                'dynamic_variables' => $dynamicVariables,
                'text_only' => true,
                'configured' => false,
            ]);
        }

        try {
            $signed = $this->elevenLabsClient->getSignedUrl($wantAdmin, $dynamicVariables);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'signed_url' => $signed['signed_url'],
            'agent_id' => $signed['agent_id'],
            'chat_token' => $chatToken,
            'dynamic_variables' => $dynamicVariables,
            'text_only' => true,
            'configured' => true,
        ]);
    }
}

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

final class ChatSignedUrlAction extends AbstractController
{
    public function __construct(
        private readonly ElevenLabsClient $elevenLabsClient,
        private readonly ChatTokenManager $chatTokenManager,
        private readonly FeatureManager $featureManager,
    ) {}

    #[Route('/api/chat/signed-url', name: 'api_chat_signed_url', methods: ['POST'])]
    public function __invoke(Request $_request): JsonResponse
    {
        if (!$this->featureManager->isEnabled('chat_assistant')) {
            return $this->json([
                'error' => 'Chat assistant is disabled',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $isLoggedIn = $user instanceof User;
        $isAdmin = $isLoggedIn && $this->isGranted('ROLE_ADMIN');

        if ($isLoggedIn) {
            $chatToken = $this->chatTokenManager->mint($user);
            $dynamicVariables = [
                'kiddo_user_id' => (string) $user->getId(),
                'kiddo_user_name' => $user->getName(),
                'kiddo_user_email' => $user->getEmail(),
                'kiddo_roles' => implode(',', $user->getRoles()),
                'kiddo_chat_token' => $chatToken,
                'kiddo_is_admin' => $isAdmin ? 'true' : 'false',
                'kiddo_is_guest' => 'false',
            ];
        } else {
            $chatToken = $this->chatTokenManager->mintGuest();
            $dynamicVariables = [
                'kiddo_user_id' => '',
                'kiddo_user_name' => '',
                'kiddo_user_email' => '',
                'kiddo_roles' => '',
                'kiddo_chat_token' => $chatToken,
                'kiddo_is_admin' => 'false',
                'kiddo_is_guest' => 'true',
            ];
        }

        if (!$this->elevenLabsClient->isConfigured()) {
            // Dev / test fallback: return token + mock WS placeholder so UI can still boot.
            return $this->json([
                'signed_url' => null,
                'agent_id' => null,
                'chat_token' => $chatToken,
                'dynamic_variables' => $dynamicVariables,
                'text_only' => true,
                'configured' => false,
                'guest' => !$isLoggedIn,
            ]);
        }

        try {
            $signed = $this->elevenLabsClient->getSignedUrl($dynamicVariables, $isAdmin);
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
            'guest' => !$isLoggedIn,
        ]);
    }
}

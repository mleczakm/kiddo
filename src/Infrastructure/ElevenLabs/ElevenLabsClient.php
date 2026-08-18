<?php

declare(strict_types=1);

namespace App\Infrastructure\ElevenLabs;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ElevenLabsClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(ELEVENLABS_API_KEY)%')]
        private string $apiKey,
        #[Autowire('%env(ELEVENLABS_AGENT_ID)%')]
        private string $agentId,
        #[Autowire('%env(ELEVENLABS_ADMIN_AGENT_ID)%')]
        private string $adminAgentId = '',
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->agentId !== '';
    }

    /**
     * @param array<string, string|int|bool|null> $dynamicVariables
     *
     * @return array{signed_url: string, agent_id: string, dynamic_variables: array<string, string|int|bool|null>}
     */
    public function getSignedUrl(array $dynamicVariables = [], bool $admin = false): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('ElevenLabs is not configured (ELEVENLABS_API_KEY / ELEVENLABS_AGENT_ID)');
        }

        $agentId = $admin && $this->adminAgentId !== '' ? $this->adminAgentId : $this->agentId;

        $response = $this->httpClient->request(
            'GET',
            'https://api.elevenlabs.io/v1/convai/conversation/get_signed_url',
            [
                'headers' => [
                    'xi-api-key' => $this->apiKey,
                ],
                'query' => [
                    'agent_id' => $agentId,
                ],
            ],
        );

        $data = $response->toArray(false);
        $signedUrl = $data['signed_url'] ?? null;
        if (!is_string($signedUrl) || $signedUrl === '') {
            throw new \RuntimeException('ElevenLabs did not return a signed_url');
        }

        return [
            'signed_url' => $signedUrl,
            'agent_id' => $agentId,
            'dynamic_variables' => $dynamicVariables,
        ];
    }
}

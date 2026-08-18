<?php

declare(strict_types=1);

namespace App\Infrastructure\Healthcheck;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use SymfonyHealthCheckBundle\Check\CheckInterface;
use SymfonyHealthCheckBundle\Dto\Response;

final readonly class HttpClientHealthcheck implements CheckInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $url,
    ) {}

    public function check(): Response
    {
        try {
            $statusCode = $this->httpClient->request('GET', $this->url, [
                'max_duration' => 5,
                'timeout' => 3,
            ])->getStatusCode();
        } catch (\Throwable $e) {
            return new Response('http_client', false, 'External HTTP client request failed: ' . $e->getMessage(), [
                'url' => $this->url,
            ]);
        }

        if ($statusCode !== 204) {
            return new Response(
                'http_client',
                false,
                sprintf('External HTTP client request returned unexpected status code %d', $statusCode),
                [
                    'url' => $this->url,
                    'status_code' => $statusCode,
                ],
            );
        }

        return new Response('http_client', true, 'External HTTP client request is healthy', [
            'url' => $this->url,
            'status_code' => $statusCode,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Healthcheck;

use App\Infrastructure\Healthcheck\HttpClientHealthcheck;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('unit')]
final class HttpClientHealthcheckTest extends TestCase
{
    private const string URL = 'https://connectivitycheck.gstatic.com/generate_204';

    public function testPassesWhenEndpointReturnsNoContent(): void
    {
        $healthcheck = new HttpClientHealthcheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 204,
        ])), self::URL);

        $response = $healthcheck->check();

        self::assertTrue($response->getResult());
        self::assertSame('http_client', $response->getName());
        self::assertSame(204, $response->getParams()['status_code']);
    }

    public function testFailsWhenEndpointReturnsUnexpectedStatusCode(): void
    {
        $healthcheck = new HttpClientHealthcheck(new MockHttpClient(new MockResponse('', [
            'http_code' => 503,
        ])), self::URL);

        $response = $healthcheck->check();

        self::assertFalse($response->getResult());
        self::assertStringContainsString('503', $response->getMessage());
        self::assertSame(503, $response->getParams()['status_code']);
    }

    public function testFailsWhenHttpClientThrows(): void
    {
        $healthcheck = new HttpClientHealthcheck(new MockHttpClient(static function (): never {
            throw new TransportException('cURL option is not supported');
        }), self::URL);

        $response = $healthcheck->check();

        self::assertFalse($response->getResult());
        self::assertStringContainsString('cURL option is not supported', $response->getMessage());
        self::assertSame(self::URL, $response->getParams()['url']);
    }
}

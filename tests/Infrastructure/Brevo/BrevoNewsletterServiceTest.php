<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Brevo;

use App\Infrastructure\Brevo\BrevoNewsletterService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('unit')]
final class BrevoNewsletterServiceTest extends TestCase
{
    private const string API_KEY = 'test-api-key';

    private const int LIST_ID = 42;

    private const int TEMPLATE_ID = 7;

    private const string REDIRECTION_URL = 'http://example.test/newsletter-confirmed';

    public function testIsConfiguredReturnsTrueWhenApiKeyAndListIdSet(): void
    {
        $service = $this->createService(new MockHttpClient([]));

        static::assertTrue($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyEmpty(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            '',
            self::LIST_ID,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );

        static::assertFalse($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenListIdZero(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            self::API_KEY,
            0,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );

        static::assertFalse($service->isConfigured());
    }

    public function testAddOrUpdateContactPostsExpectedPayload(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (
            &$captured,
        ): MockResponse {
            $captured = [
                'method' => $method,
                'url' => $url,
                'headers' => $options['headers'] ?? [],
                'body' => $options['body'] ?? null,
            ];

            return new MockResponse('', [
                'http_code' => 201,
            ]);
        });

        $service = $this->createService($httpClient);
        $service->addOrUpdateContact('User@Example.com', 'Alice');

        static::assertSame('POST', $captured['method']);
        static::assertSame('https://api.brevo.com/v3/contacts', $captured['url']);
        static::assertContains('api-key: ' . self::API_KEY, $captured['headers']);
        static::assertContains('Content-Type: application/json', $captured['headers']);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $captured['body'], true, flags: JSON_THROW_ON_ERROR);
        static::assertSame('User@Example.com', $payload['email']);
        static::assertSame([self::LIST_ID], $payload['listIds']);
        static::assertTrue($payload['updateEnabled']);
        static::assertSame(
            [
                'FIRSTNAME' => 'Alice',
            ],
            $payload['attributes'],
        );
    }

    public function testAddOrUpdateContactWithoutName(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(static function (string $_method, string $_url, array $options) use (
            &$captured,
        ): MockResponse {
            $captured['body'] = $options['body'] ?? null;

            return new MockResponse('', [
                'http_code' => 201,
            ]);
        });

        $service = $this->createService($httpClient);
        $service->addOrUpdateContact('anon@example.com');

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $captured['body'], true, flags: JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('attributes', $payload);
    }

    public function testAddOrUpdateContactThrowsWhenUnconfigured(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            '',
            self::LIST_ID,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );

        $this->expectException(\RuntimeException::class);
        $service->addOrUpdateContact('a@b.com');
    }

    public function testRemoveContactFromList(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (
            &$captured,
        ): MockResponse {
            $captured['method'] = $method;
            $captured['url'] = $url;
            $captured['body'] = $options['body'] ?? null;

            return new MockResponse('', [
                'http_code' => 204,
            ]);
        });

        $service = $this->createService($httpClient);
        $service->removeContactFromList('goodbye@example.com');

        static::assertSame('POST', $captured['method']);
        static::assertSame('https://api.brevo.com/v3/contacts/goodbye@example.com/removeList', $captured['url']);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $captured['body'], true, flags: JSON_THROW_ON_ERROR);
        static::assertSame([self::LIST_ID], $payload['listIds']);
    }

    public function testRemoveContactFromListThrowsWhenUnconfigured(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            '',
            self::LIST_ID,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );

        $this->expectException(\RuntimeException::class);
        $service->removeContactFromList('a@b.com');
    }

    public function testSendDoubleOptInConfirmationPostsExpectedPayload(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (
            &$captured,
        ): MockResponse {
            $captured['method'] = $method;
            $captured['url'] = $url;
            $captured['body'] = $options['body'] ?? null;

            return new MockResponse('', [
                'http_code' => 204,
            ]);
        });

        $service = $this->createService($httpClient);
        $service->sendDoubleOptInConfirmation('guest@example.com');

        static::assertSame('POST', $captured['method']);
        static::assertSame('https://api.brevo.com/v3/doubleOptInConfirmations', $captured['url']);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $captured['body'], true, flags: JSON_THROW_ON_ERROR);
        static::assertSame('guest@example.com', $payload['email']);
        static::assertSame([self::LIST_ID], $payload['includeListIds']);
        static::assertSame(self::TEMPLATE_ID, $payload['templateId']);
        static::assertSame(self::REDIRECTION_URL, $payload['redirectionUrl']);
    }

    public function testSendDoubleOptInConfirmationThrowsWhenUnconfigured(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            '',
            self::LIST_ID,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );

        $this->expectException(\RuntimeException::class);
        $service->sendDoubleOptInConfirmation('a@b.com');
    }

    private function createService(MockHttpClient $httpClient): BrevoNewsletterService
    {
        return new BrevoNewsletterService(
            $httpClient,
            self::API_KEY,
            self::LIST_ID,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );
    }
}

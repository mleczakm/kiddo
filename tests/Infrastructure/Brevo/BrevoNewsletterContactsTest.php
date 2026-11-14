<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Brevo;

use App\Infrastructure\Brevo\BrevoNewsletterService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('unit')]
final class BrevoNewsletterContactsTest extends BrevoNewsletterServiceTestCase
{
    public function testAddOrUpdateContactPostsExpectedPayload(): void
    {
        $captured = [
            'method' => '',
            'url' => '',
            'headers' => [],
            'body' => null,
        ];
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
        $headers = is_iterable($captured['headers']) ? $captured['headers'] : [];
        static::assertContains('api-key: ' . $this->apiKey, $headers);
        static::assertContains('Content-Type: application/json', $headers);

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
        $captured = [
            'body' => null,
        ];
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
        $captured = [
            'method' => '',
            'url' => '',
            'body' => null,
        ];
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
        static::assertSame('https://api.brevo.com/v3/contacts/goodbye%40example.com/removeList', $captured['url']);

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
}

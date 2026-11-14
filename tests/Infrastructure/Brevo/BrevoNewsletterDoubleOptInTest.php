<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Brevo;

use App\Infrastructure\Brevo\BrevoNewsletterService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('unit')]
final class BrevoNewsletterDoubleOptInTest extends BrevoNewsletterServiceTestCase
{
    public function testSendDoubleOptInConfirmationPostsExpectedPayload(): void
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
        $service->sendDoubleOptInConfirmation('guest@example.com', [
            'CONSENT_VERSION' => '2026-09-10',
        ]);

        static::assertSame('POST', $captured['method']);
        static::assertSame('https://api.brevo.com/v3/contacts/doubleOptinConfirmation', $captured['url']);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $captured['body'], true, flags: JSON_THROW_ON_ERROR);
        static::assertSame('guest@example.com', $payload['email']);
        static::assertSame([self::LIST_ID], $payload['includeListIds']);
        static::assertSame(self::TEMPLATE_ID, $payload['templateId']);
        static::assertSame(self::REDIRECTION_URL, $payload['redirectionUrl']);
        static::assertSame(
            [
                'CONSENT_VERSION' => '2026-09-10',
            ],
            $payload['attributes'],
        );
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

    public function testSendDoubleOptInConfirmationThrowsWhenTemplateIdIsZero(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            $this->apiKey,
            self::LIST_ID,
            0,
            self::REDIRECTION_URL,
        );

        $this->expectException(\RuntimeException::class);
        $service->sendDoubleOptInConfirmation('a@b.com');
    }

    public function testSendDoubleOptInConfirmationThrowsWhenRedirectionUrlIsEmpty(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            $this->apiKey,
            self::LIST_ID,
            self::TEMPLATE_ID,
            '',
        );

        $this->expectException(\RuntimeException::class);
        $service->sendDoubleOptInConfirmation('a@b.com');
    }

    public function testSendDoubleOptInConfirmationThrowsWhenBrevoRejectsRequest(): void
    {
        $service = $this->createService(new MockHttpClient([
            new MockResponse('{"code":"invalid_parameter"}', [
                'http_code' => 400,
            ]),
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Brevo API request failed with HTTP 400');
        $service->sendDoubleOptInConfirmation('a@b.com');
    }
}

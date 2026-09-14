<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Infrastructure\Brevo\BrevoNewsletterService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Group('functional')]
final class NewsletterControllerTest extends WebTestCase
{
    public function testInvalidEmailReturnsBadRequest(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);
        $brevo->expects(self::never())->method('sendDoubleOptInConfirmation');

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'not-an-email',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_BAD_REQUEST);
        $payload = $this->decode($client);
        static::assertSame('newsletter.email_invalid', $payload['error'] ?? null);
    }

    /**
     * @return array<string, array{?string, array<string, mixed>|null}>
     */
    public static function malformedRequestProvider(): array
    {
        return [
            'missing email' => ['application/json', []],
            'empty body' => [null, null],
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    #[DataProvider('malformedRequestProvider')]
    public function testMalformedRequestReturnsBadRequest(?string $contentType, ?array $payload): void
    {
        $client = static::createClient();
        $this->replaceBrevoService($client);

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: $contentType === null
                ? []
                : [
                    'CONTENT_TYPE' => $contentType,
                ],
            content: $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_BAD_REQUEST);
    }

    public function testHoneypotSilentlySucceedsWithoutTouchingBrevo(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);
        $brevo->expects(self::never())->method('sendDoubleOptInConfirmation');

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'spammer@example.com',
                'website' => 'http://spam.example',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);
        $payload = $this->decode($client);
        static::assertTrue($payload['success'] ?? false);
    }

    private function replaceBrevoService(KernelBrowser $_client): BrevoNewsletterService&MockObject
    {
        $mock = $this->createMock(BrevoNewsletterService::class);
        self::getContainer()->set(BrevoNewsletterService::class, $mock);

        return $mock;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        /** @var array<string, mixed> $data */
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}

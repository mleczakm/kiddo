<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Infrastructure\Brevo\BrevoNewsletterService;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Group('functional')]
final class NewsletterControllerTest extends WebTestCase
{
    public function testValidNewEmailTriggersDoubleOptIn(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);

        $brevo->expects(self::once())
            ->method('sendDoubleOptInConfirmation')
            ->with('new@example.com');

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'new@example.com',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);
        $payload = $this->decode($client);
        self::assertSame('newsletter.confirmation_sent', $payload['message'] ?? null);
    }

    public function testExistingSubscribedUserSkipsDoi(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);

        $brevo->expects(self::never())
            ->method('sendDoubleOptInConfirmation');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()
            ->withEmail('subscribed@example.com')
            ->withNewsletterSubscribed(true)
            ->assemble();
        $em->persist($user);
        $em->flush();

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'subscribed@example.com',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);
        $payload = $this->decode($client);
        self::assertSame('newsletter.already_subscribed', $payload['message'] ?? null);
    }

    public function testExistingUnsubscribedUserStillGoesThroughDoi(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);

        $brevo->expects(self::once())
            ->method('sendDoubleOptInConfirmation')
            ->with('unsubscribed@example.com');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()
            ->withEmail('unsubscribed@example.com')
            ->withNewsletterSubscribed(false)
            ->assemble();
        $em->persist($user);
        $em->flush();

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'unsubscribed@example.com',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);
    }

    public function testInvalidEmailReturnsBadRequest(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);
        $brevo->expects(self::never())
            ->method('sendDoubleOptInConfirmation');

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
        self::assertSame('newsletter.email_invalid', $payload['error'] ?? null);
    }

    public function testMissingEmailReturnsBadRequest(): void
    {
        $client = static::createClient();
        $this->replaceBrevoService($client);

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_BAD_REQUEST);
    }

    public function testEmptyBodyReturnsBadRequest(): void
    {
        $client = static::createClient();
        $this->replaceBrevoService($client);

        $client->request('POST', '/api/newsletter/subscribe');

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_BAD_REQUEST);
    }

    public function testHoneypotSilentlySucceedsWithoutTouchingBrevo(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);
        $brevo->expects(self::never())
            ->method('sendDoubleOptInConfirmation');

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
        self::assertTrue($payload['success'] ?? false);
    }

    public function testBrevoFailureReturnsServerError(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);
        $brevo->method('sendDoubleOptInConfirmation')
            ->willThrowException(new \RuntimeException('Brevo down'));

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'email' => 'ok@example.com',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        $payload = $this->decode($client);
        self::assertSame('newsletter.service_error', $payload['error'] ?? null);
    }

    private function replaceBrevoService(KernelBrowser $client): BrevoNewsletterService&MockObject
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
        $data = json_decode((string) $client->getResponse() ->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $data;
    }
}

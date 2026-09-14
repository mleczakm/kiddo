<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Application\Consent\MarketingConsentManager;
use App\Infrastructure\Brevo\BrevoNewsletterService;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Group('functional')]
final class NewsletterSubscriptionBrevoTest extends WebTestCase
{
    public function testExistingSubscribedUserSkipsDoi(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);

        $brevo->expects(self::never())->method('sendDoubleOptInConfirmation');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('subscribed@example.com')->withNewsletterSubscribed(true)->assemble();
        $em->persist($user);
        $em->flush();

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($this->subscriptionPayload('subscribed@example.com'), JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);
        $payload = $this->decode($client);
        static::assertSame('newsletter.already_subscribed', $payload['message'] ?? null);
    }

    public function testExistingUnsubscribedUserStillGoesThroughDoi(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);

        $brevo->method('isDoubleOptInConfigured')->willReturn(true);
        $brevo
            ->expects(self::once())
            ->method('sendDoubleOptInConfirmation')
            ->with('unsubscribed@example.com', static::callback('is_array'));

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
            content: json_encode($this->subscriptionPayload('unsubscribed@example.com'), JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);
    }

    public function testBrevoFailureReturnsServerError(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);
        $brevo->method('isDoubleOptInConfigured')->willReturn(true);
        $brevo->method('sendDoubleOptInConfirmation')->willThrowException(new \RuntimeException('Brevo down'));

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($this->subscriptionPayload('ok@example.com'), JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        $payload = $this->decode($client);
        static::assertSame('newsletter.service_error', $payload['error'] ?? null);
    }

    public function testDoubleOptInNotConfiguredReturnsServiceUnavailableWithoutCallingBrevo(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService($client);
        $brevo->method('isDoubleOptInConfigured')->willReturn(false);
        $brevo->expects(self::never())->method('sendDoubleOptInConfirmation');

        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($this->subscriptionPayload('ok@example.com'), JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        $payload = $this->decode($client);
        static::assertSame('newsletter.service_unavailable', $payload['error'] ?? null);
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

    /** @return array{email: string, consent: true, consentVersion: string} */
    private function subscriptionPayload(string $email): array
    {
        return [
            'email' => $email,
            'consent' => true,
            'consentVersion' => MarketingConsentManager::VERSION,
        ];
    }
}

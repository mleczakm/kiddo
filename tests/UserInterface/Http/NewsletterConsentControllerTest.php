<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Application\Consent\MarketingConsentManager;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Infrastructure\Brevo\BrevoNewsletterService;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Group('functional')]
final class NewsletterConsentControllerTest extends WebTestCase
{
    public function testCurrentConsentTriggersDoiWithEvidenceAttributes(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService();
        $brevo
            ->expects(self::once())
            ->method('sendDoubleOptInConfirmation')
            ->with(
                'new@example.com',
                static::callback(
                    static fn(array $attributes): bool => (
                        ($attributes['CONSENT_VERSION'] ?? null) === MarketingConsentManager::VERSION
                        && ($attributes['CONSENT_SOURCE'] ?? null) === ConsentSource::NEWSLETTER_FORM->value
                        && is_string($attributes['CONSENT_TEXT_SHA256'] ?? null)
                    ),
                ),
            );

        $this->subscribe($client, $this->subscriptionPayload('new@example.com'));

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);
        static::assertSame('newsletter.confirmation_sent', $this->decode($client)['message'] ?? null);
    }

    public function testMissingConsentReturnsBadRequest(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService();
        $brevo->expects(self::never())->method('sendDoubleOptInConfirmation');

        $this->subscribe($client, [
            'email' => 'without-consent@example.com',
        ]);

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_BAD_REQUEST);
        static::assertSame('newsletter.consent_required', $this->decode($client)['error'] ?? null);
    }

    public function testAuthenticatedUserConsentIsRecordedThroughNewsletterForm(): void
    {
        $client = static::createClient();
        $brevo = $this->replaceBrevoService();
        $brevo->expects(self::once())->method('sendDoubleOptInConfirmation');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('member@example.com')->assemble();
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);

        $this->subscribe($client, $this->subscriptionPayload('member@example.com'));

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);
        /** @var UserConsentRepository $repository */
        $repository = self::getContainer()->get(UserConsentRepository::class);
        $consents = $repository->findHistoryForUser($user);
        static::assertCount(1, $consents);
        static::assertSame(ConsentType::MARKETING_EMAIL, $consents[0]->getType());
        static::assertSame(ConsentSource::NEWSLETTER_FORM, $consents[0]->getSource());
    }

    /** @param array<string, mixed> $payload */
    private function subscribe(KernelBrowser $client, array $payload): void
    {
        $client->request(
            'POST',
            '/api/newsletter/subscribe',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function replaceBrevoService(): BrevoNewsletterService&MockObject
    {
        $mock = $this->createMock(BrevoNewsletterService::class);
        self::getContainer()->set(BrevoNewsletterService::class, $mock);

        return $mock;
    }

    /** @return array<string, mixed> */
    private function decode(KernelBrowser $client): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        return $data;
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

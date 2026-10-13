<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Application\Chat\ChatTokenManager;
use App\Application\Consent\AiConsentManager;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Infrastructure\ElevenLabs\ElevenLabsClient;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Api\ChatSignedUrlAction;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

#[Group('functional')]
final class ChatSignedUrlActionTest extends WebTestCase
{
    /**
     * @return array<array-key, mixed>
     */
    private function decode(KernelBrowser $client): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Expected a JSON object response.');
        }

        return $decoded;
    }

    public function testSignedUrlWorksForGuests(): void
    {
        $client = static::createClient();
        static::getContainer()->set(
            ElevenLabsClient::class,
            new ElevenLabsClient(
                new MockHttpClient(new MockResponse(json_encode([
                    'signed_url' => 'wss://example.test/conversation',
                ], JSON_THROW_ON_ERROR))),
                'test-api-key',
                'test-agent-id',
            ),
        );
        $client->request(
            'POST',
            '/api/chat/signed-url',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'aiConsent' => true,
                'aiConsentVersion' => AiConsentManager::VERSION,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        /** @var array{signed_url: string, guest: bool, chat_token: string, dynamic_variables: array{kiddo_is_guest: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        static::assertSame('wss://example.test/conversation', $payload['signed_url']);
        static::assertTrue($payload['guest']);
        static::assertSame('true', $payload['dynamic_variables']['kiddo_is_guest']);
        static::assertNotEmpty($payload['chat_token']);
    }

    public function testSignedUrlRequiresCurrentAiAcknowledgement(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/chat/signed-url',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{}',
        );

        self::assertResponseStatusCodeSame(428);
        /** @var array{consent_required: bool, consent_version: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        static::assertTrue($payload['consent_required']);
        static::assertSame(AiConsentManager::VERSION, $payload['consent_version']);
    }

    public function testLoggedInAcknowledgementIsRecordedAndReused(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('ai-consent@example.com')->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $client->request(
            'POST',
            '/api/chat/signed-url',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'aiConsent' => true,
                'aiConsentVersion' => AiConsentManager::VERSION,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        /** @var UserConsentRepository $repository */
        $repository = static::getContainer()->get(UserConsentRepository::class);
        $consents = $repository->findHistoryForUser($user);
        static::assertCount(1, $consents);
        static::assertSame(ConsentType::AI_USAGE, $consents[0]->getType());
        static::assertSame(ConsentSource::CHAT_ONBOARDING, $consents[0]->getSource());
        static::assertSame('ai_consent:' . AiConsentManager::VERSION, $consents[0]->getContext());

        $client->request(
            'POST',
            '/api/chat/signed-url',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{}',
        );
        self::assertResponseIsSuccessful();
    }

    public function testSignedUrlFallsBackToATokenOnlyResponseWhenElevenLabsIsNotConfigured(): void
    {
        $client = static::createClient();
        static::getContainer()->set(ElevenLabsClient::class, new ElevenLabsClient(new MockHttpClient(), '', ''));

        $this->postSignedUrl($client);

        self::assertResponseIsSuccessful();
        $payload = $this->decode($client);
        static::assertFalse($payload['configured']);
        static::assertNull($payload['signed_url']);
        static::assertNull($payload['agent_id']);
        static::assertTrue($payload['text_only']);
        static::assertTrue($payload['guest']);
        static::assertNotEmpty($payload['chat_token']);
    }

    public function testSignedUrlReportsABadGatewayWhenElevenLabsDoesNotReturnAUrl(): void
    {
        $client = static::createClient();
        static::getContainer()->set(
            ElevenLabsClient::class,
            new ElevenLabsClient(
                new MockHttpClient(new MockResponse(json_encode([], JSON_THROW_ON_ERROR))),
                'test-api-key',
                'test-agent-id',
            ),
        );

        $this->postSignedUrl($client);

        self::assertResponseStatusCodeSame(502);
        static::assertStringContainsString('signed_url', (string) $this->decode($client)['error']);
    }

    public function testSignedUrlMarksAnAdminSessionAsStaffAndUsesTheAdminAgent(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withEmail('chat-admin@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);
        static::getContainer()->set(
            ElevenLabsClient::class,
            new ElevenLabsClient(
                new MockHttpClient(new MockResponse(json_encode([
                    'signed_url' => 'wss://example.test/admin',
                ], JSON_THROW_ON_ERROR))),
                'test-api-key',
                'parent-agent',
                'admin-agent',
            ),
        );

        $this->postSignedUrl($client);

        self::assertResponseIsSuccessful();
        /** @var array{agent_id: string, guest: bool, dynamic_variables: array<string, string>} $payload */
        $payload = $this->decode($client);
        static::assertSame('admin-agent', $payload['agent_id']);
        static::assertFalse($payload['guest']);
        static::assertSame('true', $payload['dynamic_variables']['kiddo_is_admin']);
        static::assertSame('true', $payload['dynamic_variables']['kiddo_is_staff']);
        static::assertSame('false', $payload['dynamic_variables']['kiddo_is_guest']);
        static::assertSame('chat-admin@example.com', $payload['dynamic_variables']['kiddo_user_email']);
    }

    public function testSignedUrlIsHiddenWhenTheChatAssistantFlagIsOff(): void
    {
        static::bootKernel();
        $container = static::getContainer();
        $disabled = $this->createMock(FeatureManager::class);
        $disabled->method('isEnabled')->with('chat_assistant')->willReturn(false);

        /** @var ElevenLabsClient $elevenLabs */
        $elevenLabs = $container->get(ElevenLabsClient::class);
        /** @var ChatTokenManager $tokens */
        $tokens = $container->get(ChatTokenManager::class);
        /** @var AiConsentManager $consents */
        $consents = $container->get(AiConsentManager::class);

        $action = new ChatSignedUrlAction($elevenLabs, $tokens, $disabled, $consents);
        $action->setContainer($container);

        static::assertSame(404, $action(Request::create('/api/chat/signed-url', 'POST'))->getStatusCode());
    }

    private function postSignedUrl(KernelBrowser $client): void
    {
        $client->request(
            'POST',
            '/api/chat/signed-url',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'aiConsent' => true,
                'aiConsentVersion' => AiConsentManager::VERSION,
            ], JSON_THROW_ON_ERROR),
        );
    }
}

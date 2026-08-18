<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Application\Chat\ChatTokenManager;
use App\Infrastructure\ElevenLabs\ElevenLabsClient;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('functional')]
final class ChatToolsApiTest extends WebTestCase
{
    public function testListToolsWithChatToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $user = UserAssembler::new()->withEmail('chat-api@example.com')->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();

        /** @var ChatTokenManager $tokens */
        $tokens = static::getContainer()->get(ChatTokenManager::class);
        $token = $tokens->mint($user);

        $client->request('GET', '/api/v1/tools', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        /** @var array{tools: list<array{name: string}>} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $names = array_column($payload['tools'], 'name');
        self::assertContains('user.list_upcoming_lessons', $names);
        self::assertNotContains('admin.today_schedule', $names);
    }

    public function testMcpInitializeHandshake(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $em = static::getContainer()->get('doctrine')->getManager();
        $user = UserAssembler::new()->withEmail('chat-mcp@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($user);
        $em->flush();

        /** @var ChatTokenManager $tokens */
        $tokens = static::getContainer()->get(ChatTokenManager::class);
        $token = $tokens->mint($user);

        $client->request(
            'POST',
            '/api/mcp',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => [
                        'name' => 'kiddo-phpunit',
                        'version' => '1.0.0',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($client->getResponse()->headers->get('Mcp-Session-Id'));
        /** @var array{result: array{serverInfo: array{name: string}}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('kiddo', $payload['result']['serverInfo']['name']);
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
            content: '{}',
        );

        self::assertResponseIsSuccessful();
        /** @var array{signed_url: string, guest: bool, chat_token: string, dynamic_variables: array{kiddo_is_guest: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('wss://example.test/conversation', $payload['signed_url']);
        self::assertTrue($payload['guest']);
        self::assertSame('true', $payload['dynamic_variables']['kiddo_is_guest']);
        self::assertNotEmpty($payload['chat_token']);
    }

    public function testGuestTokenCanListPublicToolsButNotUserProfile(): void
    {
        $client = static::createClient();

        /** @var ChatTokenManager $tokens */
        $tokens = static::getContainer()->get(ChatTokenManager::class);
        $token = $tokens->mintGuest();

        $client->request('GET', '/api/v1/tools', server: [
            'HTTP_X_KIDDO_CHAT_TOKEN' => $token,
        ]);
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            '/api/v1/tools/user.me',
            server: [
                'HTTP_X_KIDDO_CHAT_TOKEN' => $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{}',
        );
        self::assertResponseStatusCodeSame(422);
        /** @var array{ok: bool, summary: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['ok']);
        self::assertStringContainsString('zalogować', $payload['summary']);
    }
}

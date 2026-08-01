<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Application\Chat\ChatTokenManager;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($client->getResponse()->headers->get('Mcp-Session-Id'));
        /** @var array{result: array{serverInfo: array{name: string}}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('kiddo', $payload['result']['serverInfo']['name']);
    }

    public function testSignedUrlRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/chat/signed-url');
        $status = $client->getResponse()
            ->getStatusCode();
        self::assertContains($status, [401, 302, 403]);
    }
}

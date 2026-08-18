<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Mcp;

use App\Application\Chat\ChatTokenManager;
use App\Tests\Assembler\UserAssembler;
use Mcp\Capability\RegistryInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\TerminableInterface;

#[Group('functional')]
final class KiddoMcpServerTest extends KernelTestCase
{
    public function testRegistryExposesChatTools(): void
    {
        self::bootKernel();
        // Building the server runs loaders (including KiddoChatToolLoader).
        static::getContainer()->get('mcp.server');

        /** @var RegistryInterface $registry */
        $registry = static::getContainer()->get('mcp.registry');
        $names = array_map(static fn(object $tool): string => $tool->name, [...$registry->getTools()->references]);

        static::assertContains('user_list_upcoming_lessons', $names);
        static::assertContains('user_me', $names);
        static::assertContains('admin_list_unmatched_transfers', $names);
        static::assertNotContains('create_lesson', $names);
        static::assertNotContains('admincreate_lesson', $names);
        // One public name per tool — no alias explosion for ElevenLabs.
        static::assertSame(count($names), count(array_unique($names)));
        static::assertLessThan(80, count($names));
    }

    public function testHttpInitializeThenToolsList(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $kernel = static::$kernel;
        static::assertNotNull($kernel);

        $em = $container->get('doctrine')->getManager();
        $user = UserAssembler::new()->withEmail('chat-mcp-http@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($user);
        $em->flush();

        /** @var ChatTokenManager $tokens */
        $tokens = $container->get(ChatTokenManager::class);
        $token = $tokens->mint($user);

        $init = Request::create(
            '/api/mcp',
            'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_HOST' => 'localhost',
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

        $initResponse = $kernel->handle($init);
        static::assertSame(200, $initResponse->getStatusCode(), (string) $initResponse->getContent());
        $sessionId = $initResponse->headers->get('Mcp-Session-Id');
        static::assertNotEmpty($sessionId);
        /** @var array{result: array{protocolVersion: string}} $initPayload */
        $initPayload = json_decode((string) $initResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        static::assertSame('2025-03-26', $initPayload['result']['protocolVersion']);
        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($init, $initResponse);
        }

        $list = Request::create(
            '/api/mcp',
            'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'HTTP_HOST' => 'localhost',
                'HTTP_MCP_SESSION_ID' => (string) $sessionId,
                'HTTP_MCP_PROTOCOL_VERSION' => '2025-03-26',
            ],
            content: json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ], JSON_THROW_ON_ERROR),
        );

        $listResponse = $kernel->handle($list);
        static::assertSame(200, $listResponse->getStatusCode(), (string) $listResponse->getContent());

        /** @var array{result: array{tools: list<array{name: string}>, nextCursor?: ?string}} $payload */
        $payload = json_decode((string) $listResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $names = array_column($payload['result']['tools'], 'name');

        static::assertNull($payload['result']['nextCursor'] ?? null, 'tools/list must fit on one page for ElevenLabs');
        static::assertContains('user_list_upcoming_lessons', $names);
        static::assertContains('user_get_lesson', $names);
        static::assertSame('user_list_upcoming_lessons', $names[0]);
        static::assertContains('user_me', $names);
        static::assertContains('admin_list_unmatched_transfers', $names);
        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($list, $listResponse);
        }
    }
}

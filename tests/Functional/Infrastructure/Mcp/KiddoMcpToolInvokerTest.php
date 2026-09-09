<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Mcp;

use App\Infrastructure\Mcp\KiddoMcpToolInvoker;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A missing / placeholder / expired chat token must degrade to a structured
 * ToolResult, never a thrown exception — ElevenLabs renders a thrown exception
 * as an opaque "Failed to execute MCP tool" and fails the whole turn.
 */
#[Group('functional')]
final class KiddoMcpToolInvokerTest extends KernelTestCase
{
    private function invoker(?string $chatTokenHeader): KiddoMcpToolInvoker
    {
        self::bootKernel();
        $container = static::getContainer();

        $server = [];
        if ($chatTokenHeader !== null) {
            $server['HTTP_X_KIDDO_CHAT_TOKEN'] = $chatTokenHeader;
        }

        /** @var RequestStack $stack */
        $stack = $container->get(RequestStack::class);
        $stack->push(Request::create('/api/mcp', 'POST', server: $server));

        return $container->get(KiddoMcpToolInvoker::class);
    }

    public function testPublicToolRunsAsGuestWithoutAToken(): void
    {
        $result = $this->invoker(null)->invoke('user.list_upcoming_lessons', []);

        static::assertTrue($result['ok'], (string) json_encode($result));
    }

    public function testAuthOnlyToolReturnsLoginPromptForGuestInsteadOfThrowing(): void
    {
        $result = $this->invoker(null)->invoke('user.list_bookings', []);

        static::assertFalse($result['ok']);
        static::assertStringContainsString('zalogować', $result['summary']);
    }

    public function testUnresolvedDynamicVariablePlaceholderIsTreatedAsNoToken(): void
    {
        $result = $this->invoker('{{kiddo_chat_token}}')->invoke('user.list_bookings', []);

        static::assertFalse($result['ok']);
        static::assertStringContainsString('zalogować', $result['summary']);
    }

    public function testMalformedTokenReturnsStructuredFailureInsteadOfThrowing(): void
    {
        $result = $this->invoker('not-a-real-token')->invoke('user.list_bookings', []);

        static::assertFalse($result['ok']);
        static::assertStringContainsString('Sesja czatu', $result['summary']);
    }
}

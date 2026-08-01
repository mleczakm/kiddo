<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolProviderInterface;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\ToolDefinition;
use App\Application\Chat\ToolResult;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ChatToolRegistryTest extends TestCase
{
    public function testRequiresConfirmBeforeMutation(): void
    {
        $provider = new class implements ChatToolProviderInterface {
            public function definitions(): array
            {
                return [
                    new ToolDefinition('user.demo', 'demo', [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ], requiresConfirm: true),
                ];
            }

            public function supports(string $name): bool
            {
                return $name === 'user.demo';
            }

            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                return ToolResult::success('done');
            }
        };

        $registry = new ChatToolRegistry([$provider]);
        $user = UserAssembler::new()->withId(1)->withEmail('registry@example.com')->withRoles('ROLE_USER')->assemble();
        $actor = new ChatActor($user, ['ROLE_USER']);

        $denied = $registry->call('user.demo', $actor, []);
        self::assertFalse($denied->ok);

        $ok = $registry->call('user.demo', $actor, [
            'confirm' => true,
        ]);
        self::assertTrue($ok->ok);
    }

    public function testHidesAdminToolsForParents(): void
    {
        $provider = new class implements ChatToolProviderInterface {
            public function definitions(): array
            {
                return [
                    new ToolDefinition('user.me', 'me', [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ]),
                    new ToolDefinition('admin.today_schedule', 'admin', [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ], requiresAdmin: true),
                ];
            }

            public function supports(string $name): bool
            {
                return true;
            }

            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                return ToolResult::success('ok');
            }
        };

        $registry = new ChatToolRegistry([$provider]);
        $user = UserAssembler::new()->withId(2)->withEmail('parent@example.com')->withRoles('ROLE_USER')->assemble();
        $actor = new ChatActor($user, ['ROLE_USER']);

        $names = array_map(static fn(ToolDefinition $d) => $d->name, $registry->definitions($actor));
        self::assertContains('user.me', $names);
        self::assertNotContains('admin.today_schedule', $names);
    }
}

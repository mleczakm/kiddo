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
            #[\Override]
            public function definitions(): array
            {
                return [
                    new ToolDefinition(
                        'user.demo',
                        'demo',
                        [
                            'type' => 'object',
                            'properties' => new \stdClass(),
                        ],
                        requiresConfirm: true,
                    ),
                ];
            }

            #[\Override]
            public function supports(string $name): bool
            {
                return $name === 'user.demo';
            }

            #[\Override]
            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                return ToolResult::success('done');
            }
        };

        $registry = new ChatToolRegistry([$provider]);
        $user = UserAssembler::new()->withId(1)->withEmail('registry@example.com')->withRoles('ROLE_USER')->assemble();
        $actor = new ChatActor($user, ['ROLE_USER']);

        $denied = $registry->call('user.demo', $actor, []);
        static::assertFalse($denied->ok);

        $ok = $registry->call('user.demo', $actor, [
            'confirm' => true,
        ]);
        static::assertTrue($ok->ok);
    }

    public function testHidesAdminToolsForParents(): void
    {
        $provider = new class implements ChatToolProviderInterface {
            #[\Override]
            public function definitions(): array
            {
                return [
                    new ToolDefinition('user.me', 'me', [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ]),
                    new ToolDefinition(
                        'admin.today_schedule',
                        'admin',
                        [
                            'type' => 'object',
                            'properties' => new \stdClass(),
                        ],
                        requiresAdmin: true,
                    ),
                ];
            }

            #[\Override]
            public function supports(string $name): bool
            {
                return true;
            }

            #[\Override]
            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                return ToolResult::success('ok');
            }
        };

        $registry = new ChatToolRegistry([$provider]);
        $user = UserAssembler::new()->withId(2)->withEmail('parent@example.com')->withRoles('ROLE_USER')->assemble();
        $actor = new ChatActor($user, ['ROLE_USER']);

        $names = array_map(static fn(ToolDefinition $d) => $d->name, $registry->definitions($actor));
        static::assertContains('user.me', $names);
        static::assertNotContains('admin.today_schedule', $names);
    }

    public function testGuestCannotCallAuthToolsButCanCallPublicCatalog(): void
    {
        $provider = new class implements ChatToolProviderInterface {
            #[\Override]
            public function definitions(): array
            {
                return [
                    new ToolDefinition('user.me', 'me', [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ]),
                    new ToolDefinition(
                        'user.list_upcoming_lessons',
                        'lessons',
                        [
                            'type' => 'object',
                            'properties' => new \stdClass(),
                        ],
                        requiresAuth: false,
                    ),
                ];
            }

            #[\Override]
            public function supports(string $name): bool
            {
                return true;
            }

            #[\Override]
            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                return ToolResult::success($name);
            }
        };

        $registry = new ChatToolRegistry([$provider]);
        $guest = ChatActor::guest();

        $denied = $registry->call('user.me', $guest, []);
        static::assertFalse($denied->ok);
        static::assertStringContainsString('zalogować', $denied->summary);

        $ok = $registry->call('user.list_upcoming_lessons', $guest, []);
        static::assertTrue($ok->ok);
    }

    public function testResolvesCanonicalNames(): void
    {
        $provider = new class implements ChatToolProviderInterface {
            #[\Override]
            public function definitions(): array
            {
                return [
                    new ToolDefinition(
                        'user.list_upcoming_lessons',
                        'lessons',
                        [
                            'type' => 'object',
                            'properties' => new \stdClass(),
                        ],
                        requiresAuth: false,
                    ),
                    new ToolDefinition(
                        'admin.create_booking',
                        'create',
                        [
                            'type' => 'object',
                            'properties' => new \stdClass(),
                        ],
                        requiresAdmin: true,
                        requiresConfirm: true,
                    ),
                ];
            }

            #[\Override]
            public function supports(string $name): bool
            {
                return true;
            }

            #[\Override]
            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                return ToolResult::success($name);
            }
        };

        $registry = new ChatToolRegistry([$provider]);
        $guest = ChatActor::guest();

        // Canonical name and underscore variant both work
        foreach (['user.list_upcoming_lessons', 'user_list_upcoming_lessons'] as $name) {
            $result = $registry->call($name, $guest, []);
            static::assertTrue($result->ok, $name);
            static::assertSame('user.list_upcoming_lessons', $result->summary);
        }

        $catalog = new ToolDefinition(
            'user.list_upcoming_lessons',
            'x',
            [
                'type' => 'object',
                'properties' => new \stdClass(),
            ],
            requiresAuth: false,
        );
        static::assertSame(['user_list_upcoming_lessons'], $registry->mcpNamesFor($catalog));
        static::assertSame('user_list_upcoming_lessons', $registry->mcpPublicName($catalog));

        $booking = new ToolDefinition(
            'admin.create_booking',
            'x',
            [
                'type' => 'object',
                'properties' => new \stdClass(),
            ],
            requiresAdmin: true,
            requiresConfirm: true,
        );
        static::assertSame(['admin_create_booking'], $registry->mcpNamesFor($booking));
        static::assertNull($registry->resolveCanonicalName('create_lesson'));
    }
}

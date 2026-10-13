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
final class ChatToolRegistryAccessTest extends TestCase
{
    public function testCallingAnUnknownToolFailsWithoutReachingAnyProvider(): void
    {
        $registry = new ChatToolRegistry([$this->providerOf($this->tool('user.known'))]);

        $result = $registry->call('user.missing', ChatActor::guest(), []);

        static::assertFalse($result->ok);
        static::assertStringContainsString('Unknown tool: user.missing', (string) $result->error);
        static::assertStringContainsString('user.list_upcoming_lessons', (string) $result->error);
    }

    public function testAProviderThatDoesNotClaimAToolMakesItUnknown(): void
    {
        $provider = new class implements ChatToolProviderInterface {
            #[\Override]
            public function definitions(): array
            {
                return [
                    new ToolDefinition(
                        'user.orphan',
                        'orphan',
                        [
                            'type' => 'object',
                        ],
                        requiresAuth: false,
                    ),
                ];
            }

            #[\Override]
            public function supports(string $name): bool
            {
                return false;
            }

            #[\Override]
            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                throw new \LogicException('Must not be called');
            }
        };

        $result = new ChatToolRegistry([$provider])->call('user.orphan', ChatActor::guest(), []);

        static::assertFalse($result->ok);
        static::assertSame('Unknown tool: user.orphan', $result->error);
    }

    public function testVendorPrefixesAreStrippedFromIncomingNames(): void
    {
        $registry = new ChatToolRegistry([$this->providerOf($this->tool('user.known'))]);

        foreach (['Warsztatownia_user.known', 'Kiddo_user_known', 'kiddo_user.known', '  user_known '] as $incoming) {
            static::assertSame('user.known', $registry->resolveCanonicalName($incoming), $incoming);
            static::assertTrue($registry->call($incoming, ChatActor::guest(), [])->ok, $incoming);
        }
    }

    public function testDefinitionsAreFilteredByTheActorTierUnlessAllAreRequested(): void
    {
        $registry = new ChatToolRegistry([$this->providerOf(
            $this->tool('user.public'),
            $this->tool('host.only', requiresHost: true),
            $this->tool('admin.only', requiresAdmin: true),
        )]);
        $host = new ChatActor(UserAssembler::new()->withId(2)->withRoles('ROLE_HOST')->assemble(), ['ROLE_HOST']);
        $admin = new ChatActor(UserAssembler::new()->withId(3)->withRoles('ROLE_ADMIN')->assemble(), ['ROLE_ADMIN']);

        $names = static fn(array $definitions): array => array_map(
            static fn(ToolDefinition $definition): string => $definition->name,
            $definitions,
        );

        static::assertSame(['user.public'], $names($registry->definitions()));
        static::assertSame(['user.public'], $names($registry->definitions(ChatActor::guest())));
        static::assertSame(['user.public', 'host.only'], $names($registry->definitions($host)));
        static::assertSame(['user.public', 'host.only', 'admin.only'], $names($registry->definitions($admin)));
        static::assertSame(
            ['user.public', 'host.only', 'admin.only'],
            $names($registry->definitions(includeAll: true)),
        );
    }

    public function testAdminToolsAreRefusedForNonAdminsAndHostToolsForPlainUsers(): void
    {
        $registry = new ChatToolRegistry([$this->providerOf(
            $this->tool('host.only', requiresHost: true),
            $this->tool('admin.only', requiresAdmin: true),
        )]);
        $host = new ChatActor(UserAssembler::new()->withId(2)->withRoles('ROLE_HOST')->assemble(), ['ROLE_HOST']);
        $parent = new ChatActor(UserAssembler::new()->withId(4)->withRoles('ROLE_USER')->assemble(), ['ROLE_USER']);

        static::assertSame('This tool requires ROLE_ADMIN', $registry->call('admin.only', $host, [])->error);
        static::assertSame(
            'This tool requires ROLE_HOST or ROLE_ADMIN',
            $registry->call('host.only', $parent, [])->error,
        );
        static::assertTrue($registry->call('host.only', $host, [])->ok);
    }

    private function tool(string $name, bool $requiresAdmin = false, bool $requiresHost = false): ToolDefinition
    {
        return new ToolDefinition(
            $name,
            $name,
            [
                'type' => 'object',
                'properties' => new \stdClass(),
            ],
            requiresAdmin: $requiresAdmin,
            requiresAuth: false,
            requiresHost: $requiresHost,
        );
    }

    private function providerOf(ToolDefinition ...$definitions): ChatToolProviderInterface
    {
        return new class(array_values($definitions)) implements ChatToolProviderInterface {
            /**
             * @param list<ToolDefinition> $definitions
             */
            public function __construct(
                private readonly array $definitions,
            ) {}

            #[\Override]
            public function definitions(): array
            {
                return $this->definitions;
            }

            #[\Override]
            public function supports(string $name): bool
            {
                foreach ($this->definitions as $definition) {
                    if ($definition->name === $name) {
                        return true;
                    }
                }

                return false;
            }

            #[\Override]
            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                return ToolResult::success($name);
            }
        };
    }
}

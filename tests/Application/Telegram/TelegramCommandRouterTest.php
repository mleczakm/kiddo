<?php

declare(strict_types=1);

namespace App\Tests\Application\Telegram;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolProviderInterface;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\ToolDefinition;
use App\Application\Chat\ToolResult;
use App\Application\Telegram\TelegramCommandRouter;
use App\Application\Telegram\TelegramLinkServiceInterface;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TelegramCommandRouterTest extends TestCase
{
    public function testUnlinkedUserIsAskedToLink(): void
    {
        $link = $this->createMock(TelegramLinkServiceInterface::class);
        $link->method('findLinkedUser')
            ->willReturn(null);

        $router = new TelegramCommandRouter(new ChatToolRegistry([]), $link);
        $reply = $router->handle('123', '/zajecia');

        self::assertStringContainsString('/polacz', $reply);
    }

    public function testLinkedUserCanListLessonsViaSlashCommand(): void
    {
        $user = UserAssembler::new()->withId(9)->withEmail('tg@example.com')->withRoles('ROLE_USER')->assemble();
        $link = $this->createMock(TelegramLinkServiceInterface::class);
        $link->method('findLinkedUser')
            ->willReturn($user);

        $provider = new class implements ChatToolProviderInterface {
            public function definitions(): array
            {
                return [
                    new ToolDefinition('user.list_upcoming_lessons', 'lessons', [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ]),
                ];
            }

            public function supports(string $name): bool
            {
                return $name === 'user.list_upcoming_lessons';
            }

            public function call(string $name, ChatActor $actor, array $arguments): ToolResult
            {
                return ToolResult::success('Znaleziono 1 zajęć.', [
                    'lessons' => [
                        [
                            'title' => 'Sensoryka',
                            'schedule' => '2026-08-01T10:00:00+02:00',
                            'available_spots' => 3,
                        ],
                    ],
                ]);
            }
        };

        $router = new TelegramCommandRouter(new ChatToolRegistry([$provider]), $link);
        $reply = $router->handle('123', '/zajecia sensoryka');

        self::assertStringContainsString('Znaleziono 1', $reply);
        self::assertStringContainsString('Sensoryka', $reply);
    }
}

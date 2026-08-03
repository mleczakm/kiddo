<?php

declare(strict_types=1);

namespace App\Application\Chat;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ChatToolRegistry
{
    /**
     * @param iterable<ChatToolProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.chat_tool_provider')]
        private iterable $providers,
    ) {}

    /**
     * @return list<ToolDefinition>
     */
    public function definitions(?ChatActor $actor = null, bool $includeAll = false): array
    {
        $definitions = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                if (
                    ! $includeAll
                    && $definition->requiresAdmin
                    && ($actor === null || ! $actor->isAdmin())
                ) {
                    continue;
                }
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult
    {
        foreach ($this->providers as $provider) {
            if (! $provider->supports($name)) {
                continue;
            }

            foreach ($provider->definitions() as $definition) {
                if ($definition->name !== $name) {
                    continue;
                }
                if ($definition->requiresAdmin && ! $actor->isAdmin()) {
                    return ToolResult::failure('This tool requires ROLE_ADMIN');
                }
                if ($definition->requiresAuth && $actor->isGuest()) {
                    return ToolResult::failure(
                        'Authentication required. Ask the user to log in, then refresh the chat.',
                        'Aby zobaczyć te dane lub wykonać tę akcję, musisz się zalogować. Otwórz /login, a po zalogowaniu odśwież czat.',
                    );
                }
                if ($definition->requiresConfirm && ($arguments['confirm'] ?? false) !== true) {
                    return ToolResult::failure(
                        'Mutation not confirmed. Call again with confirm=true after user approval.',
                        'Potrzebne potwierdzenie użytkownika (confirm=true).'
                    );
                }

                return $provider->call($name, $actor, $arguments);
            }
        }

        return ToolResult::failure(sprintf('Unknown tool: %s', $name));
    }
}

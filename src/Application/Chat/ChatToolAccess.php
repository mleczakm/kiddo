<?php

declare(strict_types=1);

namespace App\Application\Chat;

final class ChatToolAccess
{
    public function isVisible(ToolDefinition $definition, ?ChatActor $actor): bool
    {
        if ($definition->permissions->requiresAdmin && $actor?->isAdmin() !== true) {
            return false;
        }

        return !$definition->permissions->requiresHost || $actor?->isHost() === true;
    }

    /** @param array<string, mixed> $arguments */
    public function denial(ToolDefinition $definition, ChatActor $actor, array $arguments): ?ToolResult
    {
        if ($definition->permissions->requiresAdmin && !$actor->isAdmin()) {
            return ToolResult::failure('This tool requires ROLE_ADMIN');
        }
        if ($definition->permissions->requiresHost && !$actor->isHost()) {
            return ToolResult::failure(
                'This tool requires ROLE_HOST or ROLE_ADMIN',
                'Ta akcja jest dostępna tylko dla prowadzących zajęcia i administratorów.',
            );
        }
        if ($definition->permissions->requiresAuth && $actor->isGuest()) {
            return ToolResult::failure(
                'Authentication required. Ask the user to log in, then refresh the chat.',
                'Aby zobaczyć te dane lub wykonać tę akcję, musisz się zalogować. Otwórz /login, a po zalogowaniu odśwież czat.',
            );
        }
        if ($definition->permissions->requiresConfirm && ($arguments['confirm'] ?? false) !== true) {
            return ToolResult::failure(
                'Mutation not confirmed. Call again with confirm=true after user approval.',
                'Potrzebne potwierdzenie użytkownika (confirm=true).',
            );
        }

        return null;
    }
}

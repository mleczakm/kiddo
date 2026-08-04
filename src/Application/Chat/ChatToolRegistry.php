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
     * Resolve ElevenLabs / LLM aliases to the canonical tool name (e.g. list_upcoming_lessons → user.list_upcoming_lessons).
     */
    public function resolveCanonicalName(string $name): ?string
    {
        $normalized = $this->normalizeIncomingName($name);
        $aliases = $this->aliasMap();

        return $aliases[$normalized] ?? null;
    }

    /**
     * @return list<string> All MCP-visible names for a canonical tool (canonical + aliases).
     */
    public function mcpNamesFor(ToolDefinition $definition): array
    {
        $shortCounts = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->definitions() as $candidate) {
                $short = $this->shortName($candidate->name);
                if ($short !== null) {
                    $shortCounts[$short] = ($shortCounts[$short] ?? 0) + 1;
                }
            }
        }

        return $this->mcpNamesForDefinition($definition->name, $shortCounts);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult
    {
        $canonical = $this->resolveCanonicalName($name);
        if ($canonical === null) {
            return ToolResult::failure(sprintf(
                'Unknown tool: %s. For workshops/catalog use user.list_upcoming_lessons (aliases: list_upcoming_lessons, user_list_upcoming_lessons).',
                $name
            ));
        }

        foreach ($this->providers as $provider) {
            if (! $provider->supports($canonical)) {
                continue;
            }

            foreach ($provider->definitions() as $definition) {
                if ($definition->name !== $canonical) {
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

                return $provider->call($canonical, $actor, $arguments);
            }
        }

        return ToolResult::failure(sprintf('Unknown tool: %s', $name));
    }

    /**
     * @return array<string, string> alias → canonical
     */
    private function aliasMap(): array
    {
        $map = [];
        $shortCounts = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                $short = $this->shortName($definition->name);
                if ($short !== null) {
                    $shortCounts[$short] = ($shortCounts[$short] ?? 0) + 1;
                }
            }
        }

        foreach ($this->providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                $canonical = $definition->name;
                foreach ($this->mcpNamesForDefinition($canonical, $shortCounts) as $alias) {
                    $map[$this->normalizeIncomingName($alias)] = $canonical;
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string, int> $shortCounts
     *
     * @return list<string>
     */
    private function mcpNamesForDefinition(string $canonical, array $shortCounts): array
    {
        $names = [
            $canonical,
            str_replace('.', '_', $canonical),
        ];

        // user.list_upcoming_lessons → userlist_upcoming_lessons (dots stripped)
        $names[] = str_replace('.', '', $canonical);

        $short = $this->shortName($canonical);
        if ($short !== null && ($shortCounts[$short] ?? 0) === 1) {
            $names[] = $short;
        }

        return array_values(array_unique($names));
    }

    private function shortName(string $canonical): ?string
    {
        if (str_starts_with($canonical, 'user.')) {
            return substr($canonical, strlen('user.'));
        }
        if (str_starts_with($canonical, 'admin.')) {
            return substr($canonical, strlen('admin.'));
        }

        return null;
    }

    private function normalizeIncomingName(string $name): string
    {
        $name = trim($name);
        // ElevenLabs sometimes prefixes with the MCP server label.
        foreach (['Warsztatownia_', 'Kiddo_', 'kiddo_'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                break;
            }
        }

        return $name;
    }
}

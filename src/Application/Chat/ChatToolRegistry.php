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
     * Resolve MCP / LLM tool names to the canonical registry name.
     */
    public function resolveCanonicalName(string $name): ?string
    {
        $normalized = $this->normalizeIncomingName($name);
        $map = $this->aliasMap();

        return $map[$normalized] ?? $map[str_replace('.', '_', $normalized)] ?? null;
    }

    /**
     * Single public MCP name per tool (ElevenLabs truncates large catalogs).
     */
    public function mcpPublicName(ToolDefinition $definition): string
    {
        if ($definition->mcpAliases !== []) {
            return $definition->mcpAliases[0];
        }

        return str_replace('.', '_', $definition->name);
    }

    /**
     * @return list<string>
     */
    public function mcpNamesFor(ToolDefinition $definition): array
    {
        return [$this->mcpPublicName($definition)];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult
    {
        $canonical = $this->resolveCanonicalName($name);
        if ($canonical === null) {
            return ToolResult::failure(sprintf(
                'Unknown tool: %s. To list workshops call browse_workshops.',
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
     * Incoming aliases accepted on call (not all are advertised on MCP).
     *
     * @return array<string, string> alias → canonical
     */
    private function aliasMap(): array
    {
        $map = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                $canonical = $definition->name;
                $candidates = [
                    $canonical,
                    str_replace('.', '_', $canonical),
                    $this->mcpPublicName($definition),
                    ...$definition->mcpAliases,
                ];
                foreach ($candidates as $alias) {
                    if ($alias === '') {
                        continue;
                    }
                    $map[$this->normalizeIncomingName($alias)] = $canonical;
                }
            }
        }

        return $map;
    }

    private function normalizeIncomingName(string $name): string
    {
        $name = trim($name);
        foreach (['Warsztatownia_', 'Kiddo_', 'kiddo_'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $name = substr($name, strlen($prefix));
                break;
            }
        }

        return $name;
    }
}

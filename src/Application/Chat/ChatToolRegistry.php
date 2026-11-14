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
        private ChatToolAccess $access = new ChatToolAccess(),
        private ChatToolNames $names = new ChatToolNames(),
    ) {}

    /**
     * @return list<ToolDefinition>
     */
    public function definitions(?ChatActor $actor = null, bool $includeAll = false): array
    {
        $definitions = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                if (!$includeAll && !$this->access->isVisible($definition, $actor)) {
                    continue;
                }
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    public function resolveCanonicalName(string $name): ?string
    {
        return $this->names->resolve($name, $this->providers);
    }

    /**
     * Single public MCP name per tool (ElevenLabs truncates large catalogs).
     */
    public function mcpPublicName(ToolDefinition $definition): string
    {
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
                'Unknown tool: %s. To list workshops call user.list_upcoming_lessons.',
                $name,
            ));
        }

        foreach ($this->providers as $provider) {
            if (!$provider->supports($canonical)) {
                continue;
            }

            foreach ($provider->definitions() as $definition) {
                if ($definition->name !== $canonical) {
                    continue;
                }
                $denial = $this->access->denial($definition, $actor, $arguments);
                if ($denial !== null) {
                    return $denial;
                }

                return $provider->call($canonical, $actor, $arguments);
            }
        }

        return ToolResult::failure(sprintf('Unknown tool: %s', $name));
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\ToolDefinition;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;

/**
 * Registers exactly one MCP name per Kiddo chat tool (avoids ElevenLabs truncation).
 */
final readonly class KiddoChatToolLoader implements LoaderInterface
{
    public function __construct(
        private ChatToolRegistry $chatToolRegistry,
        private KiddoMcpToolInvoker $invoker,
    ) {}

    #[\Override]
    public function load(RegistryInterface $registry): void
    {
        $definitions = $this->chatToolRegistry->definitions(null, includeAll: true);
        usort($definitions, $this->registrationOrder(...));

        foreach ($definitions as $definition) {
            $canonical = $definition->name;
            $mcpName = $this->chatToolRegistry->mcpPublicName($definition);
            $invoker = $this->invoker;
            $handler = \Closure::bind(
                /**
                 * @param array<string, mixed> $arguments
                 * @throws \LogicException
                 * @throws \RuntimeException
                 */
                static fn(array $arguments): mixed => $invoker->invoke($canonical, $arguments),
                null,
                ReferenceHandler::class,
            );

            $registry->registerTool(
                new Tool(
                    name: $mcpName,
                    title: $mcpName,
                    inputSchema: $this->normalizeInputSchema($definition),
                    description: $this->description($definition),
                    annotations: new ToolAnnotations(
                        readOnlyHint: !$definition->permissions->requiresConfirm,
                        destructiveHint: $definition->permissions->requiresConfirm,
                        idempotentHint: !$definition->permissions->requiresConfirm,
                        openWorldHint: false,
                    ),
                ),
                $handler,
            );
        }
    }

    /**
     * Catalog / read tools first so they survive client-side tool-list limits.
     */
    private function registrationOrder(ToolDefinition $a, ToolDefinition $b): int
    {
        return $this->priority($a) <=> $this->priority($b);
    }

    private function priority(ToolDefinition $definition): int
    {
        if (in_array($definition->name, ['user.list_upcoming_lessons', 'user.get_lesson'], true)) {
            return 0;
        }
        if (!$definition->permissions->requiresConfirm && !$definition->permissions->requiresAdmin) {
            return 1;
        }
        if (!$definition->permissions->requiresConfirm) {
            return 2;
        }

        return 3;
    }

    /**
     * @return array{type: 'object', properties: array<string, mixed>, required: list<string>}
     */
    private function normalizeInputSchema(ToolDefinition $definition): array
    {
        $schema = $definition->inputSchema;
        /** @var mixed $properties */
        $properties = $schema['properties'] ?? [];
        if ($properties instanceof \stdClass) {
            $properties = [];
        }
        if (!is_array($properties)) {
            $properties = [];
        }

        $required = [];
        if (is_array($schema['required'] ?? null)) {
            $required = array_values(array_filter($schema['required'], is_string(...)));
        }

        /** @var array<string, mixed> $properties */
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    private function description(ToolDefinition $definition): string
    {
        $description = $definition->description;
        if ($definition->permissions->requiresConfirm) {
            $description .= ' Requires confirm=true before mutation.';
        }
        if ($definition->permissions->requiresAdmin) {
            $description .= ' Requires ROLE_ADMIN.';
        }
        if (!$definition->permissions->requiresAuth) {
            $description .= ' Public (no login).';
        }

        return $description;
    }
}

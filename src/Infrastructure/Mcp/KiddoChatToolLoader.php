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
 * Registers Kiddo ChatToolRegistry definitions with the Symfony MCP Bundle registry.
 */
final readonly class KiddoChatToolLoader implements LoaderInterface
{
    public function __construct(
        private ChatToolRegistry $chatToolRegistry,
        private KiddoMcpToolInvoker $invoker,
    ) {}

    public function load(RegistryInterface $registry): void
    {
        foreach ($this->chatToolRegistry->definitions(null, includeAll: true) as $definition) {
            $toolName = $definition->name;
            $invoker = $this->invoker;

            // Bound to ReferenceHandler scope so the SDK passes the raw argument bag.
            $handler = \Closure::bind(
                static fn(array $arguments): mixed => $invoker->invoke($toolName, $arguments),
                null,
                ReferenceHandler::class
            );

            $registry->registerTool(
                new Tool(
                    name: $definition->name,
                    title: $definition->name,
                    inputSchema: $this->normalizeInputSchema($definition),
                    description: $this->description($definition),
                    annotations: new ToolAnnotations(
                        readOnlyHint: ! $definition->requiresConfirm,
                        destructiveHint: $definition->requiresConfirm,
                        idempotentHint: ! $definition->requiresConfirm,
                        openWorldHint: false,
                    ),
                ),
                $handler,
            );
        }
    }

    /**
     * @return array{type: 'object', properties: array<string, mixed>, required: list<string>|null}
     */
    private function normalizeInputSchema(ToolDefinition $definition): array
    {
        $schema = $definition->inputSchema;
        $properties = $schema['properties'] ?? [];
        if ($properties instanceof \stdClass) {
            $properties = [];
        }
        if (! is_array($properties)) {
            $properties = [];
        }

        $required = null;
        if (isset($schema['required']) && is_array($schema['required'])) {
            /** @var list<string> $required */
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
        if ($definition->requiresConfirm) {
            $description .= ' Requires confirm=true in arguments before mutation.';
        }
        if ($definition->requiresAdmin) {
            $description .= ' Requires ROLE_ADMIN chat token.';
        }

        return $description;
    }
}

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
 *
 * Also registers aliases (underscores / short names) so ElevenLabs / LLMs that drop
 * the `user.` prefix still hit the same handler.
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
            $canonical = $definition->name;
            $invoker = $this->invoker;
            $handler = \Closure::bind(
                static fn(array $arguments): mixed => $invoker->invoke($canonical, $arguments),
                null,
                ReferenceHandler::class
            );

            foreach ($this->chatToolRegistry->mcpNamesFor($definition) as $mcpName) {
                $registry->registerTool(
                    new Tool(
                        name: $mcpName,
                        title: $canonical,
                        inputSchema: $this->normalizeInputSchema($definition),
                        description: $this->description($definition, $mcpName, $canonical),
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

    private function description(ToolDefinition $definition, string $mcpName, string $canonical): string
    {
        $description = $definition->description;
        if ($mcpName !== $canonical) {
            $description = sprintf('Alias of %s. %s', $canonical, $description);
        }
        if ($definition->requiresConfirm) {
            $description .= ' Requires confirm=true in arguments before mutation.';
        }
        if ($definition->requiresAdmin) {
            $description .= ' Requires ROLE_ADMIN chat token.';
        }
        if (! $definition->requiresAuth) {
            $description .= ' Available without login (public catalog).';
        }

        return $description;
    }
}

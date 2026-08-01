<?php

declare(strict_types=1);

namespace App\Application\Chat;

interface ChatToolProviderInterface
{
    /**
     * @return list<ToolDefinition>
     */
    public function definitions(): array;

    public function supports(string $name): bool;

    /**
     * @param array<string, mixed> $arguments
     */
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult;
}

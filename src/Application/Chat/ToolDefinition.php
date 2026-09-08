<?php

declare(strict_types=1);

namespace App\Application\Chat;

final readonly class ToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema object
     * @param list<string> $mcpAliases Extra MCP/LLM names that map to this tool (e.g. browse_workshops)
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public bool $requiresAdmin = false,
        public bool $requiresConfirm = false,
        public bool $requiresAuth = true,
        public array $mcpAliases = [],
        /**
         * Staff tier: available to workshop instructors (ROLE_HOST) and
         * admins. Implied by requiresAdmin.
         */
        public bool $requiresHost = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMcpTool(): array
    {
        $description = $this->description;
        if ($this->requiresConfirm) {
            $description .= ' Requires confirm=true in arguments before mutation.';
        }
        if ($this->requiresHost && !$this->requiresAdmin) {
            $description .= ' Requires ROLE_HOST (workshop instructor) or ROLE_ADMIN.';
        }
        if (!$this->requiresAuth) {
            $description .= ' Available without login (public catalog).';
        } else {
            $description .= ' Requires a logged-in parent; guests receive a login prompt.';
        }

        return [
            'name' => $this->name,
            'description' => $description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}

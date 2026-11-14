<?php

declare(strict_types=1);

namespace App\Application\Chat;

final readonly class ToolDefinition
{
    /**
     * @param array<string, mixed> $inputSchema JSON Schema object
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public ToolPermissions $permissions = new ToolPermissions(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMcpTool(): array
    {
        $description = $this->description;
        if ($this->permissions->requiresConfirm) {
            $description .= ' Requires confirm=true in arguments before mutation.';
        }
        if ($this->permissions->requiresHost && !$this->permissions->requiresAdmin) {
            $description .= ' Requires ROLE_HOST (workshop instructor) or ROLE_ADMIN.';
        }
        if (!$this->permissions->requiresAuth) {
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

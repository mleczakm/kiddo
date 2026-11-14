<?php

declare(strict_types=1);

namespace App\Application\Chat;

final readonly class ToolPermissions
{
    public function __construct(
        public bool $requiresAdmin = false,
        public bool $requiresConfirm = false,
        public bool $requiresAuth = true,
        public bool $requiresHost = false,
    ) {}
}

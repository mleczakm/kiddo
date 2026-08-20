<?php

declare(strict_types=1);

namespace App\Application\Templating;

interface TemplateRendererInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string;
}

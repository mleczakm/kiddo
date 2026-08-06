<?php

declare(strict_types=1);

namespace App\Application\Chat;

final readonly class ToolResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public bool $ok,
        public string $summary,
        public array $data = [],
        public ?string $error = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function success(string $summary, array $data = []): self
    {
        return new self(true, $summary, $data);
    }

    public static function failure(string $error, string $summary = ''): self
    {
        return new self(false, $summary !== '' ? $summary : $error, [], $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'summary' => $this->summary,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}

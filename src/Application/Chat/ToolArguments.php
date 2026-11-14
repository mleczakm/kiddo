<?php

declare(strict_types=1);

namespace App\Application\Chat;

final readonly class ToolArguments
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private array $arguments,
    ) {}

    /** @throws \InvalidArgumentException */
    public function string(string $key, ?string $default = null): ?string
    {
        /** @var mixed $value */
        $value = $this->arguments[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (in_array(gettype($value), ['string', 'integer', 'double'], true)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException(sprintf('Argument "%s" must be a string', $key));
    }

    /** @throws \InvalidArgumentException */
    public function requireString(string $key): string
    {
        $value = $this->string($key) ?? '';
        if ($value === '') {
            throw new \InvalidArgumentException(sprintf('Argument "%s" is required', $key));
        }

        return $value;
    }

    /** @throws \InvalidArgumentException */
    public function int(string $key, ?int $default = null): ?int
    {
        /** @var mixed $value */
        $value = $this->arguments[$key] ?? $default;
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException(sprintf('Argument "%s" must be an integer', $key));
    }

    /** @throws \InvalidArgumentException */
    public function requireInt(string $key): int
    {
        $value = $this->int($key);
        if ($value === null) {
            throw new \InvalidArgumentException(sprintf('Argument "%s" is required', $key));
        }

        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        /** @var mixed $value */
        $value = $this->arguments[$key] ?? null;
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => $default,
        };
    }

    public function has(string $key): bool
    {
        return !in_array($this->arguments[$key] ?? null, [null, ''], true);
    }

    /**
     * @return array<int|string, mixed>|null
     * @throws \InvalidArgumentException
     */
    public function array(string $key): ?array
    {
        /** @var mixed $value */
        $value = $this->arguments[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('Argument "%s" must be an array', $key));
        }

        return $value;
    }
}

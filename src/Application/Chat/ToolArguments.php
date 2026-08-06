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

    public function string(string $key, ?string $default = null): ?string
    {
        if (! array_key_exists($key, $this->arguments)) {
            return $default;
        }
        $value = $this->arguments[$key];
        if ($value === null) {
            return $default;
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException(sprintf('Argument "%s" must be a string', $key));
    }

    public function requireString(string $key): string
    {
        $value = $this->string($key);
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException(sprintf('Argument "%s" is required', $key));
        }

        return $value;
    }

    public function int(string $key, ?int $default = null): ?int
    {
        if (! array_key_exists($key, $this->arguments)) {
            return $default;
        }
        $value = $this->arguments[$key];
        if ($value === null) {
            return $default;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }

        throw new \InvalidArgumentException(sprintf('Argument "%s" must be an integer', $key));
    }

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
        if (! array_key_exists($key, $this->arguments)) {
            return $default;
        }
        $value = $this->arguments[$key];
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists(
            $key,
            $this->arguments
        ) && $this->arguments[$key] !== null && $this->arguments[$key] !== '';
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function array(string $key): ?array
    {
        if (! array_key_exists($key, $this->arguments)) {
            return null;
        }
        $value = $this->arguments[$key];
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new \InvalidArgumentException(sprintf('Argument "%s" must be an array', $key));
        }

        /** @var array<int|string, mixed> $value */
        return $value;
    }
}

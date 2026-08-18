<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Symfony\Component\Uid\Ulid;

/** Reads and validates one persisted lesson-map entry. */
final readonly class LessonMapFieldReader
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private array $data,
    ) {}

    /**
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     */
    public function requiredUlid(string $field): Ulid
    {
        if (!array_key_exists($field, $this->data)) {
            throw new \UnexpectedValueException(sprintf('Lesson map field "%s" must be a ULID string.', $field));
        }
        if (!is_string($this->data[$field]) || $this->data[$field] === '') {
            throw new \UnexpectedValueException(sprintf('Lesson map field "%s" must be a ULID string.', $field));
        }

        return Ulid::fromString($this->data[$field]);
    }

    /** @throws \UnexpectedValueException */
    public function optionalInt(string $field): ?int
    {
        if (($this->data[$field] ?? null) === null) {
            return null;
        }
        if (!is_int($this->data[$field]) && !is_string($this->data[$field])) {
            throw new \UnexpectedValueException(sprintf('Lesson map field "%s" must be an integer.', $field));
        }

        return (
            filter_var(
                $this->data[$field],
                FILTER_VALIDATE_INT,
                FILTER_NULL_ON_FAILURE,
            ) ?? throw new \UnexpectedValueException(sprintf('Lesson map field "%s" must be an integer.', $field))
        );
    }

    /** @throws \UnexpectedValueException */
    public function optionalDate(string $field): ?\DateTimeImmutable
    {
        if (($this->data[$field] ?? null) === null) {
            return null;
        }
        if (!is_string($this->data[$field])) {
            throw new \UnexpectedValueException(sprintf('Lesson map field "%s" must be a date string.', $field));
        }

        return new \DateTimeImmutable($this->data[$field]);
    }

    /** @throws \UnexpectedValueException */
    public function optionalString(string $field): ?string
    {
        if (($this->data[$field] ?? null) === null) {
            return null;
        }
        if (is_string($this->data[$field])) {
            return $this->data[$field];
        }

        throw new \UnexpectedValueException(sprintf('Lesson map field "%s" must be a string.', $field));
    }
}

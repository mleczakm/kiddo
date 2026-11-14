<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Serializer;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Uid\Ulid;

#[Exclude]
final readonly class LessonMapEntry
{
    private function __construct(
        public string $id,
        public mixed $data,
    ) {}

    public static function from(string $key, mixed $data): ?self
    {
        if (Ulid::isValid($key)) {
            return new self($key, $data);
        }

        if (is_string($data) && Ulid::isValid($data)) {
            // Older payloads store a list of lesson IDs without keys.
            return new self($data, ['lessonId' => $data]);
        }

        if (!is_array($data)) {
            return null;
        }

        /** @var mixed $candidate */
        $candidate =
            $data['lessonId']
            ?? $data['id']
            ?? (is_array($data['lesson'] ?? null) ? $data['lesson']['id'] ?? null : null);

        return is_string($candidate) && Ulid::isValid($candidate) ? new self($candidate, $data) : null;
    }
}

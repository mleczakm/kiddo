<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Serializer;

use App\Entity\DTO\BookedLesson;
use Ds\Map;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Uid\Ulid;

final class LessonMapDenormalizer
{
    /**
     * @return Map<Ulid, BookedLesson>
     * @param array<string, mixed> $context
     * @throws ExceptionInterface
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     */
    public function denormalize(
        mixed $mapData,
        ?string $format,
        array $context,
        DenormalizerInterface $denormalizer,
    ): Map {
        /** @var Map<Ulid, BookedLesson> $result */
        $result = new Map();
        if (!is_iterable($mapData)) {
            return $result;
        }

        if ($mapData instanceof \Traversable) {
            $mapData = iterator_to_array($mapData);
        }

        foreach (array_keys($mapData) as $lessonId) {
            /** @var mixed $lessonData */
            $lessonData = $mapData[$lessonId];
            $entry = LessonMapEntry::from((string) $lessonId, $lessonData);
            if ($entry === null) {
                continue;
            }
            /** @var mixed $lessonData */
            $lessonData = $entry->data;

            // Detect object type from 'type' field, default to BookedLesson
            /** @var mixed $typeField */
            $typeField = is_array($lessonData) ? $lessonData['type'] ?? null : null;
            $class = is_string($typeField) ? $typeField : BookedLesson::class;

            /** @var mixed $lesson */
            $lesson = $denormalizer->denormalize($lessonData, $class, $format, $context);
            if (!$lesson instanceof BookedLesson) {
                throw new \UnexpectedValueException('Expected a booked lesson.');
            }
            $result->put(Ulid::fromString($entry->id), $lesson);
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Serializer;

use App\Entity\DTO\BookedLesson;
use App\Entity\DTO\LessonMap;
use Ds\Map;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\Ulid;

class LessonMapNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param array<string, mixed> $context
     * @throws ExceptionInterface
     * @throws \LogicException
     */
    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $payload = is_array($data) ? $data : [];
        $lessonMap = new LessonMap();
        $lessonMap->lessons = $this->denormalizeMap($payload['lessons'] ?? [], $format, $context);
        $lessonMap->active = $this->denormalizeMap($payload['active'] ?? [], $format, $context);
        $lessonMap->past = $this->denormalizeMap($payload['past'] ?? [], $format, $context);
        $lessonMap->cancelled = $this->denormalizeMap($payload['cancelled'] ?? [], $format, $context);
        return $lessonMap;
    }

    #[\Override]
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return $type === LessonMap::class;
    }

    /**
     * @return array{lessons: array<mixed>, active: array<mixed>, past: array<mixed>, cancelled: array<mixed>}
     * @param array<string, mixed> $context
     * @throws ExceptionInterface
     */
    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof LessonMap) {
            throw new \InvalidArgumentException();
        }
        return [
            'lessons' => $this->normalizeMap($data->lessons, $format, $context),
            'active' => $this->normalizeMap($data->active, $format, $context),
            'past' => $this->normalizeMap($data->past, $format, $context),
            'cancelled' => $this->normalizeMap($data->cancelled, $format, $context),
        ];
    }

    /**
     * @return Map<Ulid, BookedLesson>
     * @param array<string, mixed> $context
     * @throws ExceptionInterface
     * @throws \LogicException
     */
    private function denormalizeMap(mixed $mapData, ?string $format, array $context): Map
    {
        /** @var Map<Ulid, BookedLesson> $result */
        $result = new Map();
        if (!is_iterable($mapData)) {
            return $result;
        }

        foreach ($mapData as $lessonId => $lessonData) {
            // Force string for the key
            $lessonIdStr = (string) $lessonId;

            // If the key is not a valid ULID (e.g. numeric index like "0"), try to extract from payload
            if (!Ulid::isValid($lessonIdStr)) {
                $candidate = null;
                if (is_array($lessonData)) {
                    // Common shapes produced by nested normalizers
                    $candidate =
                        $lessonData['lessonId']
                        ?? $lessonData['id']
                        ?? (is_array($lessonData['lesson'] ?? null) ? $lessonData['lesson']['id'] ?? null : null);
                } elseif (is_string($lessonData) && Ulid::isValid($lessonData)) {
                    // Handle legacy form: list of ULID strings with numeric keys
                    $candidate = $lessonData;
                    // Also convert payload into a shape the inner normalizer can understand
                    $lessonData = [
                        'lessonId' => $candidate,
                    ];
                }

                if (is_string($candidate) && Ulid::isValid($candidate)) {
                    $lessonIdStr = $candidate;
                } else {
                    // Skip entries we cannot identify – better than throwing during rendering
                    continue;
                }
            }

            // Detect object type from 'type' field, default to BookedLesson
            $typeField = is_array($lessonData) ? $lessonData['type'] ?? null : null;
            $class = is_string($typeField) ? $typeField : BookedLesson::class;

            if (!$this->normalizer instanceof DenormalizerInterface) {
                throw new \LogicException('Normalizer must implement DenormalizerInterface');
            }
            $result->put(
                Ulid::fromString($lessonIdStr),
                $this->normalizer->denormalize($lessonData, $class, $format, $context),
            );
        }

        return $result;
    }

    /**
     * @param Map<Ulid, BookedLesson> $map
     * @return array<string, mixed>
     * @param array<string, mixed> $context
     * @throws ExceptionInterface
     */
    private function normalizeMap(Map $map, ?string $format, array $context): array
    {
        $result = [];
        foreach ($map as $lessonId => $lesson) {
            $normalized = $this->normalizer->normalize($lesson, $format, $context);
            // Ensure type info is present to allow correct denormalization later
            if (is_array($normalized)) {
                $normalized['type'] ??= $lesson::class;
            }
            $result[$lessonId->toString()] = $normalized;
        }

        return $result;
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof LessonMap;
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
        ];
    }
}

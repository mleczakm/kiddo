<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Serializer;

use Ds\Map;
use Symfony\Component\Uid\Ulid;
use App\Entity\DTO\BookedLesson;
use App\Entity\DTO\LessonMap;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class LessonMapNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $denormalizeMap = function ($mapData) use ($format, $context): Map {
            /** @var Map<Ulid, BookedLesson> $result */
            $result = new Map();
            if (! is_iterable($mapData)) {
                return $result;
            }
            foreach ($mapData as $lessonId => $lessonData) {
                // Force string for the key
                $lessonIdStr = (string) $lessonId;

                // If the key is not a valid ULID (e.g. numeric index like "0"), try to extract from payload
                if (! Ulid::isValid($lessonIdStr)) {
                    $candidate = null;
                    if (is_array($lessonData)) {
                        // Common shapes produced by nested normalizers
                        $candidate = $lessonData['lessonId']
                            ?? ($lessonData['id'] ?? null)
                            ?? ($lessonData['lesson']['id'] ?? null);
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
                $typeField = is_array($lessonData) ? ($lessonData['type'] ?? null) : null;
                $class = is_string($typeField) ? $typeField : BookedLesson::class;

                if (! $this->normalizer instanceof DenormalizerInterface) {
                    throw new \LogicException('Normalizer must implement DenormalizerInterface');
                }
                $result->put(
                    Ulid::fromString($lessonIdStr),
                    $this->normalizer->denormalize($lessonData, $class, $format, $context)
                );
            }
            return $result;
        };

        $payload = is_array($data) ? $data : [];
        $lessonMap = new LessonMap();
        $lessonMap->lessons   = $denormalizeMap($payload['lessons'] ?? []);
        $lessonMap->active    = $denormalizeMap($payload['active'] ?? []);
        $lessonMap->past      = $denormalizeMap($payload['past'] ?? []);
        $lessonMap->cancelled = $denormalizeMap($payload['cancelled'] ?? []);
        return $lessonMap;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === LessonMap::class;
    }

    /**
     * @return array{lessons: array<mixed>, active: array<mixed>, past: array<mixed>, cancelled: array<mixed>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (! $data instanceof LessonMap) {
            throw new \InvalidArgumentException();
        }
        $normalizeMap = function ($map) use ($format, $context) {
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
        };
        return [
            'lessons'   => $normalizeMap($data->lessons),
            'active'    => $normalizeMap($data->active),
            'past'      => $normalizeMap($data->past),
            'cancelled' => $normalizeMap($data->cancelled),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof LessonMap;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
        ];
    }
}

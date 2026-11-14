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

    public function __construct(
        private readonly LessonMapDenormalizer $mapDenormalizer = new LessonMapDenormalizer(),
    ) {}

    /**
     * @param array<string, mixed> $context
     * @throws ExceptionInterface
     * @throws \LogicException
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     */
    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!$this->normalizer instanceof DenormalizerInterface) {
            throw new \LogicException('Normalizer must implement DenormalizerInterface');
        }
        $payload = is_array($data) ? $data : [];
        $lessonMap = new LessonMap();
        $lessonMap->lessons = $this->mapDenormalizer->denormalize(
            $payload['lessons'] ?? [],
            $format,
            $context,
            $this->normalizer,
        );
        $lessonMap->active = $this->mapDenormalizer->denormalize(
            $payload['active'] ?? [],
            $format,
            $context,
            $this->normalizer,
        );
        $lessonMap->past = $this->mapDenormalizer->denormalize(
            $payload['past'] ?? [],
            $format,
            $context,
            $this->normalizer,
        );
        $lessonMap->cancelled = $this->mapDenormalizer->denormalize(
            $payload['cancelled'] ?? [],
            $format,
            $context,
            $this->normalizer,
        );
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
     * @throws \InvalidArgumentException
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

<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Entity\DTO\BookedLesson;
use App\Entity\DTO\CancelledLesson;
use App\Entity\DTO\RescheduledLesson;
use Ds\Map;
use Symfony\Component\Uid\Ulid;

final class LessonMapHydrator
{
    /**
     * @return Map<Ulid, BookedLesson>
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     */
    public function hydrate(mixed $mapData): Map
    {
        /** @var Map<Ulid, BookedLesson> $map */
        $map = new Map();
        if (!is_array($mapData)) {
            return $map;
        }

        foreach (array_keys($mapData) as $key) {
            $lesson = $this->hydrateLesson($key, $mapData[$key]);
            $mapKey = is_string($key) ? Ulid::fromString($key) : $lesson->lessonId;
            $map->put($mapKey, $lesson);
        }

        return $map;
    }

    /**
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException
     */
    private function hydrateLesson(int|string $key, mixed $itemData): BookedLesson
    {
        if (is_string($itemData)) {
            return new BookedLesson(Ulid::fromString($itemData));
        }
        if (!is_array($itemData)) {
            throw new \UnexpectedValueException('Lesson map entries must be strings or objects.');
        }

        /** @var array<string, mixed> $itemData */
        if (!array_key_exists('lessonId', $itemData) && is_string($key)) {
            $itemData['lessonId'] = $key;
        }
        $fields = new LessonMapFieldReader($itemData);
        $lessonId = $fields->requiredUlid('lessonId');

        if (array_key_exists('rescheduledFrom', $itemData)) {
            return new RescheduledLesson(
                $lessonId,
                $fields->requiredUlid('rescheduledFrom'),
                $fields->optionalInt('rescheduledBy') ?? 0,
                $fields->optionalDate('rescheduledAt'),
            );
        }
        if (array_key_exists('cancelledAt', $itemData)) {
            return new CancelledLesson(
                $lessonId,
                $fields->optionalInt('cancelledBy'),
                $fields->optionalDate('cancelledAt'),
                $fields->optionalString('reason'),
            );
        }

        return new BookedLesson($lessonId);
    }
}

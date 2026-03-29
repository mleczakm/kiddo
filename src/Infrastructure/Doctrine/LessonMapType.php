<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Entity\DTO\BookedLesson;
use App\Entity\DTO\LessonMap;
use App\Entity\DTO\RescheduledLesson;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use Ds\Map;
use Symfony\Component\Uid\Ulid;

class LessonMapType extends JsonType
{
    public const NAME = 'lesson_map';

    #[\Override]
    public function getName(): string
    {
        return self::NAME;
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?LessonMap
    {
        if ($value === null || $value === '') {
            return null;
        }

        $data = parent::convertToPHPValue($value, $platform);

        if (! is_array($data)) {
            return new LessonMap();
        }

        $lessonMap = new LessonMap();

        $deserializeMap = function (mixed $mapData): Map {
            if (! is_array($mapData)) {
                return new Map();
            }

            $map = new Map();
            foreach ($mapData as $key => $itemData) {
                $ulid = is_string($key) ? Ulid::fromString($key) : Ulid::fromString(
                    is_string($itemData) ? $itemData : (is_array(
                        $itemData
                    ) && isset($itemData['lessonId']) ? (string) $itemData['lessonId'] : '')
                );
                if (is_array($itemData) && isset($itemData['rescheduledFrom'])) {
                    $map->put($ulid, new RescheduledLesson(
                        Ulid::fromString(isset($itemData['lessonId']) ? (string) $itemData['lessonId'] : ''),
                        Ulid::fromString((string) $itemData['rescheduledFrom']),
                        isset($itemData['rescheduledBy']) ? (int) $itemData['rescheduledBy'] : 0,
                        isset($itemData['rescheduledAt']) ? new \DateTimeImmutable(
                            (string) $itemData['rescheduledAt']
                        ) : null
                    ));
                } else {
                    $map->put($ulid, new BookedLesson($ulid));
                }
            }
            return $map;
        };

        $lessonMap->lessons = $deserializeMap($data['lessons'] ?? []);
        $lessonMap->active = $deserializeMap($data['active'] ?? []);
        $lessonMap->past = $deserializeMap($data['past'] ?? []);
        $lessonMap->cancelled = $deserializeMap($data['cancelled'] ?? []);

        return $lessonMap;
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (! $value instanceof LessonMap) {
            return null;
        }

        $serializeMap = function (Map $map): array {
            $result = [];
            foreach ($map as $key => $val) {
                if (! $key instanceof Ulid || ! $val instanceof BookedLesson) {
                    continue;
                }
                $result[$key->toString()] = $this->serializeBookedLesson($val);
            }
            return $result;
        };

        $data = [
            'lessons' => $serializeMap($value->lessons),
            'active' => $serializeMap($value->active),
            'past' => $serializeMap($value->past),
            'cancelled' => $serializeMap($value->cancelled),
        ];

        return parent::convertToDatabaseValue($data, $platform);
    }

    /**
     * @return array{lessonId: string, rescheduledFrom?: string, rescheduledBy?: int, rescheduledAt?: string|null}
     */
    private function serializeBookedLesson(BookedLesson $bookedLesson): array
    {
        $data = [
            'lessonId' => $bookedLesson->lessonId->toString(),
        ];
        if ($bookedLesson instanceof RescheduledLesson) {
            $data['rescheduledFrom'] = $bookedLesson->rescheduledFrom->toString();
            $data['rescheduledBy'] = $bookedLesson->rescheduledBy;
            $data['rescheduledAt'] = $bookedLesson->rescheduledAt?->format(\DateTimeInterface::RFC3339);
        }
        return $data;
    }

    #[\Override]
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}

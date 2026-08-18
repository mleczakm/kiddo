<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Entity\DTO\BookedLesson;
use App\Entity\DTO\CancelledLesson;
use App\Entity\DTO\LessonMap;
use App\Entity\DTO\RescheduledLesson;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use Ds\Map;
use Symfony\Component\Uid\Ulid;

class LessonMapType extends JsonType
{
    public const NAME = 'lesson_map';

    /**
     * @throws \UnexpectedValueException When stored lesson data has an invalid shape.
     * @throws \Symfony\Component\Uid\Exception\InvalidArgumentException When a stored ULID is invalid.
     * @throws \Doctrine\DBAL\Types\ConversionException When the JSON value cannot be decoded.
     */
    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?LessonMap
    {
        if ($value === null || $value === '') {
            return null;
        }

        $data = parent::convertToPHPValue($value, $platform);

        if (!is_array($data)) {
            return new LessonMap();
        }

        $lessonMap = new LessonMap();
        $hydrator = new LessonMapHydrator();
        $lessonMap->lessons = $hydrator->hydrate($data['lessons'] ?? []);
        $lessonMap->active = $hydrator->hydrate($data['active'] ?? []);
        $lessonMap->past = $hydrator->hydrate($data['past'] ?? []);
        $lessonMap->cancelled = $hydrator->hydrate($data['cancelled'] ?? []);

        return $lessonMap;
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (!$value instanceof LessonMap) {
            return null;
        }

        $serializeMap = function (Map $map): array {
            $result = [];
            foreach ($map as $key => $val) {
                if (!$key instanceof Ulid || !$val instanceof BookedLesson) {
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
     * @return array{lessonId: string, rescheduledFrom?: string, rescheduledBy?: int, rescheduledAt?: string|null, cancelledBy?: int|null, cancelledAt?: string|null, reason?: string|null}
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
        } elseif ($bookedLesson instanceof CancelledLesson) {
            $data['cancelledBy'] = $bookedLesson->cancelledBy;
            $data['cancelledAt'] = $bookedLesson->cancelledAt?->format(\DateTimeInterface::RFC3339);
            $data['reason'] = $bookedLesson->reason;
        }
        return $data;
    }
}

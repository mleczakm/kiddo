<?php

declare(strict_types=1);

namespace App\Entity\DTO;

use App\Entity\Booking;
use App\Entity\Lesson;
use Ds\Map;
use Symfony\Component\Uid\Ulid;

class LessonMap implements \Countable, \JsonSerializable
{
    private Map $lessons;

    private Map $cancelled;

    private Map $past;

    private Map $active;

    public function __construct(array ...$datas)
    {
        $reduceMap = function (Map $lessons, int $index, string $lessonId): Map {
            $lessons->put($id = Ulid::fromString($lessonId), new BookedLesson($id));

            return $lessons;
        };
        foreach ($datas as $key => $data) {
            switch ($key) {
                case 'lessons':
                    $this->lessons = new Map(... $data)
                        ->reduce($reduceMap, new Map());
                    break;
                case 'past':
                    $this->past = new Map(...$data)
                        ->reduce($reduceMap, new Map());
                    break;
                case 'cancelled':
                    $this->cancelled = new Map(...$data)
                        ->reduce($reduceMap, new Map());
                    break;
                case 'active':
                    $this->active = new Map(...$data)
                        ->reduce($reduceMap, new Map());
                    break;

            }
        }

        $this->cancelled ??= new Map();
        $this->past ??= new Map();
        $this->active ??= new Map();
        $this->lessons ??= new Map();
    }

    private static function create(self $self) {}

    public static function createFromBooking(Booking $booking): self
    {
        $lessonMap = new self([]);
        foreach ($booking->getLessons() as $lesson) {
            $lessonId = $lesson->getId();

            if ($booking->getStatus() === Booking::STATUS_PAST) {
                $lessonMap->past->put($lessonId, new BookedLesson($lessonId));
            } elseif ($booking->getStatus() === Booking::STATUS_ACTIVE) {
                $lessonMap->active->put($lessonId, new BookedLesson($lessonId));
            } elseif ($booking->getStatus() === Booking::STATUS_CANCELLED) {
                $lessonMap->cancelled->put($lessonId, new BookedLesson($lessonId));
            }

            $lessonMap->lessons->put($lessonId, new BookedLesson($lessonId));
        }

        return $lessonMap;
    }

    public function count(): int
    {
        return $this->lessons->count();
    }

    public function jsonSerialize(): array
    {
        return [
            'lessons' => $this->lessons->map(fn(BookedLesson $lesson): string => (string) $lesson->lessonId->toString())
                ->toArray(),
            'active' => $this->active->map(fn(BookedLesson $lesson): string => (string) $lesson->lessonId->toString())
                ->toArray(),
            'past' => $this->past->map(fn(BookedLesson $lesson): string => (string) $lesson->lessonId->toString())
                ->toArray(),
            'cancelled' => $this->lessons->map(
                fn(BookedLesson $lesson): string => (string) $lesson->lessonId->toString()
            )
                ->toArray(),
        ];
    }

    public function active(): Map
    {
        return clone $this->active;
    }

    public function cancelled(): Map
    {
        return clone $this->cancelled;
    }

    public function past(): Map
    {
        return clone $this->past;
    }

    public function entities(Booking $booking): \Generator
    {
        yield from $booking->getLessons()
            ->filter(fn(Lesson $lesson): bool => $this->lessons->hasKey($lesson->getId()));
    }

    public function entity(Booking $booking): \Generator
    {
        yield from $booking->getLessons()
            ->filter(fn(Lesson $lesson): bool => $this->lessons->hasKey($lesson->getId()));
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\DTO;

use App\Entity\Booking;
use App\Entity\Lesson;
use Ds\Map;
use Symfony\Component\Uid\Ulid;

class LessonMap implements \Countable
{
    /**
     * @var Map<Ulid, BookedLesson>
     */
    public Map $lessons;

    /**
     * @var Map<Ulid, BookedLesson>
     */
    public Map $cancelled;

    /**
     * @var Map<Ulid, BookedLesson>
     */
    public Map $past;

    /**
     * @var Map<Ulid, BookedLesson>
     */
    public Map $active;

    /**
     * @param array<string, array<string>> ...$datas
     */
    public function __construct(array ...$datas)
    {
        $reduceMap = function (Map $lessons, int $index, string $lessonId): Map {
            $lessons->put($id = Ulid::fromString($lessonId), new BookedLesson($id));

            return $lessons;
        };

        foreach ($datas as $key => $data) {
            switch ($key) {
                case 'lessons':
                    $this->lessons = new Map($data)
                        ->reduce($reduceMap, new Map());
                    break;
                case 'past':
                    $this->past = new Map($data)
                        ->reduce($reduceMap, new Map());
                    break;
                case 'cancelled':
                    $this->cancelled = new Map($data)
                        ->reduce($reduceMap, new Map());
                    break;
                case 'active':
                    $this->active = new Map($data)
                        ->reduce($reduceMap, new Map());
                    break;
            }
        }

        $this->cancelled ??= new Map();
        $this->past ??= new Map();
        $this->active ??= new Map();
        $this->lessons ??= new Map();
    }

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

    /**
     * @return array{lessons: array<string>, active: array<string>, past: array<string>, cancelled: array<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'lessons' => $this->lessons->map(fn(BookedLesson $lesson): string => (string) $lesson->lessonId->toString())
                ->toArray(),
            'active' => $this->active->map(fn(BookedLesson $lesson): string => (string) $lesson->lessonId->toString())
                ->toArray(),
            'past' => $this->past->map(fn(BookedLesson $lesson): string => (string) $lesson->lessonId->toString())
                ->toArray(),
            'cancelled' => $this->cancelled->map(
                fn(BookedLesson $lesson): string => (string) $lesson->lessonId->toString()
            )
                ->toArray(),
        ];
    }

    /**
     * @return Map<Ulid, BookedLesson>
     */
    public function active(): Map
    {
        return clone $this->active;
    }

    /**
     * @return Map<Ulid, BookedLesson>
     */
    public function cancelled(): Map
    {
        return clone $this->cancelled;
    }

    /**
     * @return Map<Ulid, BookedLesson>
     */
    public function past(): Map
    {
        return clone $this->past;
    }

    // Add missing methods that are called in Booking.php
    public function setLesson(string $lessonId, BookedLesson $bookedLesson): void
    {
        $ulid = Ulid::fromString($lessonId);
        $this->lessons->put($ulid, $bookedLesson);
        $this->active->put($ulid, $bookedLesson);
    }

    public function removeLesson(Ulid $lessonId): void
    {
        $this->lessons->remove($lessonId);
        $this->active->remove($lessonId);
        $this->cancelled->remove($lessonId);
        $this->past->remove($lessonId);
    }

    public function cancelAllBookedLessons(?string $reason = null): void
    {
        // Move all active lessons to cancelled
        foreach ($this->active as $ulid => $bookedLesson) {
            $this->cancelled->put($ulid, $bookedLesson);
        }
        $this->active->clear();
    }

    public function getLesson(string $lessonId): ?BookedLesson
    {
        $ulid = Ulid::fromString($lessonId);
        return $this->lessons->get($ulid, null);
    }

    public function cancelLesson(string $lessonId, ?string $reason = null): bool
    {
        $ulid = Ulid::fromString($lessonId);
        if ($this->active->hasKey($ulid)) {
            $bookedLesson = $this->active->get($ulid);
            $this->cancelled->put($ulid, $bookedLesson);
            $this->active->remove($ulid);
            return true;
        }
        return false;
    }

    public function refundLesson(string $lessonId, ?string $reason = null): bool
    {
        $ulid = Ulid::fromString($lessonId);
        if ($this->active->hasKey($ulid)) {
            $bookedLesson = $this->active->get($ulid);
            $this->cancelled->put($ulid, $bookedLesson); // For simplicity, treating refunded as cancelled
            $this->active->remove($ulid);
            return true;
        }
        return false;
    }

    public function hasActiveBookedLessons(): bool
    {
        return $this->active->count() > 0;
    }

    public function areAllActiveLessonsInPast(Booking $booking): bool
    {
        $now = new \DateTimeImmutable();
        foreach ($this->active as $bookedLesson) {
            $lesson = $bookedLesson->entity($booking);
            if ($lesson && $lesson->getMetadata()->schedule > $now) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return BookedLesson[]
     */
    public function getBooked(): array
    {
        return $this->active->toArray();
    }

    /**
     * @return BookedLesson[]
     */
    public function getCancelled(): array
    {
        return $this->cancelled->toArray();
    }

    /**
     * @return BookedLesson[]
     */
    public function getRefunded(): array
    {
        return $this->cancelled->toArray(); // For simplicity
    }

    /**
     * @return BookedLesson[]
     */
    public function getRescheduled(): array
    {
        return []; // Not implemented yet
    }

    /**
     * @return BookedLesson[]
     */
    public function getModifiableLessons(Booking $booking): array
    {
        $now = new \DateTimeImmutable();
        $modifiable = [];
        foreach ($this->active as $bookedLesson) {
            $lesson = $bookedLesson->entity($booking);
            if ($lesson && $lesson->getMetadata()->schedule > $now) {
                $modifiable[] = $bookedLesson;
            }
        }
        return $modifiable;
    }

    /**
     * @return array{total: int, booked: int, cancelled: int, refunded: int, rescheduled: int}
     */
    public function getStatusSummary(): array
    {
        return [
            'total' => $this->lessons->count(),
            'booked' => $this->active->count(),
            'cancelled' => $this->cancelled->count(),
        ];
    }

    /**
     * @return BookedLesson[]
     */
    public function getPastActiveLessons(Booking $booking): array
    {
        return $this->past->toArray();
    }

    /**
     * @return BookedLesson[]
     */
    public function getFutureActiveLessons(Booking $booking): array
    {
        $now = new \DateTimeImmutable();
        $future = [];
        foreach ($this->active as $bookedLesson) {
            $lesson = $bookedLesson->entity($booking);
            if ($lesson && $lesson->getMetadata()->schedule > $now) {
                $future[] = $bookedLesson;
            }
        }
        return $future;
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

    public function getActiveCount(): int
    {
        return $this->active->count();
    }

    public function getPastCount(): int
    {
        return $this->past->count();
    }

    public function getCancelledCount(): int
    {
        return $this->cancelled->count();
    }

    public function getTotalCount(): int
    {
        return $this->lessons->count();
    }

    public function isCancelledLesson(Ulid|string $lessonId): bool
    {
        $ulid = $lessonId instanceof Ulid ? $lessonId : Ulid::fromString((string) $lessonId);

        return $this->cancelled->hasKey($ulid);
    }

    public function isRescheduledLesson(Ulid|string $lessonId): bool
    {
        $ulid = $lessonId instanceof Ulid ? $lessonId : Ulid::fromString((string) $lessonId);
        if (! $this->cancelled->hasKey($ulid)) {
            return false;
        }
        $value = $this->cancelled->get($ulid);
        return $value instanceof RescheduledLesson;
    }

    public function isActiveLesson(Ulid|string $lessonId): bool
    {
        $ulid = $lessonId instanceof Ulid ? $lessonId : Ulid::fromString((string) $lessonId);

        return $this->active->hasKey($ulid);
    }
}

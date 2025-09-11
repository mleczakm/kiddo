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
     * @param array<string, list<string>> ...$datas
     */
    public function __construct(array ...$datas)
    {
        // Initialize empty maps
        /** @var Map<Ulid, BookedLesson> $cancelled */
        $cancelled = new Map();
        /** @var Map<Ulid, BookedLesson> $past */
        $past = new Map();
        /** @var Map<Ulid, BookedLesson> $active */
        $active = new Map();
        /** @var Map<Ulid, BookedLesson> $lessons */
        $lessons = new Map();
        $this->cancelled = $cancelled;
        $this->past = $past;
        $this->active = $active;
        $this->lessons = $lessons;

        // Support passing either a single associative array with keys or variadic keyed arrays
        foreach ($datas as $key => $data) {
            if (is_string($key)) {
                $payload = [
                    $key => $data,
                ];
            } else {
                $payload = $data; // expect associative array with optional keys
            }

            if (isset($payload['lessons'])) {
                foreach ((array) $payload['lessons'] as $lessonId) {
                    if (is_string($lessonId)) {
                        $id = Ulid::fromString($lessonId);
                        $this->lessons->put($id, new BookedLesson($id));
                    }
                }
            }
            if (isset($payload['past'])) {
                foreach ((array) $payload['past'] as $lessonId) {
                    if (is_string($lessonId)) {
                        $id = Ulid::fromString($lessonId);
                        $this->past->put($id, new BookedLesson($id));
                    }
                }
            }
            if (isset($payload['cancelled'])) {
                foreach ((array) $payload['cancelled'] as $lessonId) {
                    if (is_string($lessonId)) {
                        $id = Ulid::fromString($lessonId);
                        $this->cancelled->put($id, new BookedLesson($id));
                    }
                }
            }
            if (isset($payload['active'])) {
                foreach ((array) $payload['active'] as $lessonId) {
                    if (is_string($lessonId)) {
                        $id = Ulid::fromString($lessonId);
                        $this->active->put($id, new BookedLesson($id));
                    }
                }
            }
        }
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
        $lessons = [];
        foreach ($this->lessons as $lesson) {
            $lessons[] = $lesson->lessonId->toString();
        }
        $active = [];
        foreach ($this->active as $lesson) {
            $active[] = $lesson->lessonId->toString();
        }
        $past = [];
        foreach ($this->past as $lesson) {
            $past[] = $lesson->lessonId->toString();
        }
        $cancelled = [];
        foreach ($this->cancelled as $lesson) {
            $cancelled[] = $lesson->lessonId->toString();
        }

        return [
            'lessons' => $lessons,
            'active' => $active,
            'past' => $past,
            'cancelled' => $cancelled,
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
        $result = [];
        foreach ($this->active as $booked) {
            $result[] = $booked;
        }
        return $result;
    }

    /**
     * @return BookedLesson[]
     */
    public function getCancelled(): array
    {
        $result = [];
        foreach ($this->cancelled as $booked) {
            $result[] = $booked;
        }
        return $result;
    }

    /**
     * @return BookedLesson[]
     */
    public function getRefunded(): array
    {
        $result = [];
        foreach ($this->cancelled as $booked) {
            $result[] = $booked;
        }
        return $result; // For simplicity
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
        // At the moment, refunded and rescheduled are represented via the cancelled map entries or not tracked separately.
        // Return zero for those counters to satisfy the complete shape required by static analysis.
        return [
            'total' => $this->lessons->count(),
            'booked' => $this->active->count(),
            'cancelled' => $this->cancelled->count(),
            'refunded' => 0,
            'rescheduled' => 0,
        ];
    }

    /**
     * @return BookedLesson[]
     */
    public function getPastActiveLessons(Booking $booking): array
    {
        $result = [];
        foreach ($this->past as $booked) {
            $result[] = $booked;
        }
        return $result;
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

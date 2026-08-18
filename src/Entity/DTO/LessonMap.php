<?php

declare(strict_types=1);

namespace App\Entity\DTO;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use Ds\Map;
use Symfony\Component\Clock\Clock;
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
     * PHP's default clone is shallow: the four Ds\Map buckets would still
     * point at the same underlying maps as the original, so mutating a
     * clone (e.g. moving a lesson from active to cancelled) would silently
     * mutate the "original" too — defeating the whole point of cloning
     * before reassigning, which is what lets Doctrine's reference-based
     * change tracking notice the entity changed (see Booking::cancelLesson()
     * / rescheduleLesson()). Clone each bucket explicitly to keep the two
     * copies independent.
     */
    public function __clone(): void
    {
        $this->lessons = clone $this->lessons;
        $this->active = clone $this->active;
        $this->past = clone $this->past;
        $this->cancelled = clone $this->cancelled;
    }

    public function __construct()
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
    }

    public static function createFromBooking(Booking $booking): self
    {
        $lessonMap = new self();
        $now = Clock::get()->now();
        foreach ($booking->getLessons() as $lesson) {
            $lessonId = $lesson->getId();
            $booked = new BookedLesson($lessonId);

            // Always register in the full list
            $lessonMap->lessons->put($lessonId, $booked);

            // If the whole booking is cancelled, mark lessons as cancelled
            if ($booking->getStatus() === Booking::STATUS_CANCELLED) {
                $lessonMap->cancelled->put($lessonId, $booked);
                continue;
            }

            // Otherwise, classify by the lesson's schedule
            $schedule = $lesson->schedule;
            if ($schedule >= $now) {
                $lessonMap->active->put($lessonId, $booked);
            } else {
                $lessonMap->past->put($lessonId, $booked);
            }
        }

        return $lessonMap;
    }

    #[\Override]
    public function count(): int
    {
        return $this->lessons->count();
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

    public function cancelAllBookedLessons(?string $reason = null, ?User $cancelledBy = null): void
    {
        $cancelledAt = new \DateTimeImmutable();
        // Move all active lessons to cancelled
        foreach ($this->active as $ulid => $bookedLesson) {
            $this->cancelled->put($ulid, new CancelledLesson($ulid, $cancelledBy?->getId(), $cancelledAt, $reason));
        }
        /** @var Map<Ulid, BookedLesson> $active */
        $active = new Map();
        $this->active = $active;
    }

    /**
     * Moves lessons from the cancelled bucket back to active (or past, if
     * their schedule has since elapsed), mirroring cancelAllBookedLessons().
     */
    public function reactivateAllBookedLessons(Booking $booking): void
    {
        $now = Clock::get()->now();

        foreach ($booking->getLessons() as $lesson) {
            $lessonId = $lesson->getId();
            if (!$this->cancelled->hasKey($lessonId)) {
                continue;
            }

            $this->cancelled->remove($lessonId);
            if ($lesson->schedule >= $now) {
                $this->active->put($lessonId, new BookedLesson($lessonId));
            } else {
                $this->past->put($lessonId, new BookedLesson($lessonId));
            }
        }
    }

    public function getLesson(string $lessonId): ?BookedLesson
    {
        $ulid = Ulid::fromString($lessonId);
        return $this->lessons->get($ulid, null);
    }

    public function cancelLesson(string $lessonId, ?string $reason = null, ?User $cancelledBy = null): bool
    {
        $ulid = Ulid::fromString($lessonId);
        if ($this->active->hasKey($ulid)) {
            $this->cancelled->put(
                $ulid,
                new CancelledLesson($ulid, $cancelledBy?->getId(), new \DateTimeImmutable(), $reason),
            );
            $this->active->remove($ulid);
            return true;
        }
        return false;
    }

    public function refundLesson(string $lessonId, ?string $_reason = null): bool
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

    public function rescheduleLesson(Lesson $from, Lesson $to, User $rescheduledBy): void
    {
        $fromId = $from->getId();
        $toId = $to->getId();

        // Add the new lesson to the main lessons list and active list
        $this->lessons->put($toId, new BookedLesson($toId));
        $this->active->put($toId, new BookedLesson($toId));

        // Move the original lesson from active to cancelled
        if ($this->active->hasKey($fromId)) {
            $this->active->remove($fromId);
        }

        // Mark the original lesson as rescheduled in the cancelled map
        $this->cancelled->put(
            $fromId,
            new RescheduledLesson($toId, $fromId, $rescheduledBy->getId() ?? 0, new \DateTimeImmutable()),
        );
    }

    public function hasActiveBookedLessons(): bool
    {
        return $this->active->count() > 0;
    }

    /**
     * A cancelled lesson has nothing left to happen, so it doesn't need a date
     * check - only a still-active lesson scheduled in the future should block
     * the booking from being considered done.
     */
    public function areAllLessonsInPast(Booking $booking): bool
    {
        $now = Clock::get()->now();
        foreach ($this->lessons as $lessonId => $bookedLesson) {
            if ($this->cancelled->hasKey($lessonId)) {
                continue;
            }
            $lesson = $bookedLesson->entity($booking);
            if ($lesson && $lesson->schedule > $now) {
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
        $result = [];
        foreach ($this->cancelled as $booked) {
            if (!$booked instanceof RescheduledLesson) {
                continue;
            }

            $result[] = $booked;
        }
        return $result;
    }

    /**
     * @return BookedLesson[]
     */
    public function getModifiableLessons(Booking $booking): array
    {
        $now = Clock::get()->now();
        $modifiable = [];
        foreach ($this->active as $bookedLesson) {
            $lesson = $bookedLesson->entity($booking);
            if ($lesson && $lesson->schedule > $now) {
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
    public function getPastActiveLessons(Booking $_booking): array
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
        $now = Clock::get()->now();
        $future = [];
        foreach ($this->active as $bookedLesson) {
            $lesson = $bookedLesson->entity($booking);
            if ($lesson && $lesson->schedule > $now) {
                $future[] = $bookedLesson;
            }
        }
        return $future;
    }

    public function entities(Booking $booking): \Generator
    {
        yield from $booking->getLessons()->filter(fn(Lesson $lesson): bool => $this->lessons->hasKey($lesson->getId()));
    }

    public function entity(Booking $booking): \Generator
    {
        yield from $booking->getLessons()->filter(fn(Lesson $lesson): bool => $this->lessons->hasKey($lesson->getId()));
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
        $ulid = $lessonId instanceof Ulid ? $lessonId : Ulid::fromString($lessonId);

        return $this->cancelled->hasKey($ulid);
    }

    public function isRescheduledLesson(Ulid|string $lessonId): bool
    {
        $ulid = $lessonId instanceof Ulid ? $lessonId : Ulid::fromString($lessonId);
        if (!$this->cancelled->hasKey($ulid)) {
            return false;
        }
        $value = $this->cancelled->get($ulid);
        return $value instanceof RescheduledLesson;
    }

    public function isActiveLesson(Ulid|string $lessonId): bool
    {
        $ulid = $lessonId instanceof Ulid ? $lessonId : Ulid::fromString($lessonId);

        return $this->active->hasKey($ulid);
    }

    /**
     * Raw entry for a lesson in the cancelled bucket (plain BookedLesson,
     * CancelledLesson, or RescheduledLesson depending on how/when it was
     * cancelled — older records may only have the plain shape).
     */
    public function getCancelledEntry(Ulid|string $lessonId): ?BookedLesson
    {
        $ulid = $lessonId instanceof Ulid ? $lessonId : Ulid::fromString($lessonId);

        return $this->cancelled->get($ulid, null);
    }

    public function getCancelledByUserId(Ulid|string $lessonId): ?int
    {
        $entry = $this->getCancelledEntry($lessonId);

        return $entry instanceof CancelledLesson ? $entry->cancelledBy : null;
    }

    public function getCancelledAt(Ulid|string $lessonId): ?\DateTimeImmutable
    {
        $entry = $this->getCancelledEntry($lessonId);

        return $entry instanceof CancelledLesson ? $entry->cancelledAt : null;
    }

    public function getCancellationReason(Ulid|string $lessonId): ?string
    {
        $entry = $this->getCancelledEntry($lessonId);

        return $entry instanceof CancelledLesson ? $entry->reason : null;
    }

    public function getRescheduledByUserId(Ulid|string $lessonId): ?int
    {
        $entry = $this->getCancelledEntry($lessonId);

        return $entry instanceof RescheduledLesson ? $entry->rescheduledBy : null;
    }

    public function getRescheduledAt(Ulid|string $lessonId): ?\DateTimeImmutable
    {
        $entry = $this->getCancelledEntry($lessonId);

        return $entry instanceof RescheduledLesson ? $entry->rescheduledAt : null;
    }
}

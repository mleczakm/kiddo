<?php

declare(strict_types=1);

namespace App\Application\Service\Waitlist;

use App\Application\Repository\WaitlistEntryRepositoryInterface;
use App\Entity\Lesson;
use App\Entity\User;
use App\Entity\WaitlistEntry;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

/**
 * Lesson-waitlist queue: join / leave for the caller, and the seat mechanics
 * (promote waiting entries to held offers when seats free up, expire stale
 * offers). Notification is delegated to {@see WaitlistOfferMailer}. This is the
 * single place both the chat tool and the web modal go through.
 */
final readonly class WaitlistService
{
    public function __construct(
        private WaitlistEntryRepositoryInterface $waitlist,
        private EntityManagerInterface $em,
        private WaitlistOfferMailer $mailer,
        private FeatureManager $featureManager,
    ) {}

    /** Whether a parent can be offered the waitlist for this lesson at all. */
    public function isAvailableFor(Lesson $lesson): bool
    {
        return $this->featureManager->isEnabled('waitlist') && $lesson->isWaitlistEnabled(true);
    }

    public function join(User $user, Lesson $lesson): WaitlistJoinResult
    {
        if (!$this->isAvailableFor($lesson)) {
            return new WaitlistJoinResult(WaitlistJoinOutcome::Unavailable);
        }
        if ($lesson->getAvailableSpots() > 0) {
            return new WaitlistJoinResult(WaitlistJoinOutcome::SeatAvailable);
        }

        $existing = $this->waitlist->findActiveForUserAndLesson($user, $lesson);
        if ($existing !== null) {
            return new WaitlistJoinResult(
                WaitlistJoinOutcome::AlreadyQueued,
                $existing,
                $this->positionOf($existing, $lesson),
            );
        }

        $entry = new WaitlistEntry($lesson, $user, $user->getEmail(), $user->getName());
        $this->em->persist($entry);
        $this->em->flush();

        return new WaitlistJoinResult(WaitlistJoinOutcome::Joined, $entry, $this->positionOf($entry, $lesson));
    }

    /** @return bool whether an active entry was found and cancelled */
    public function leave(User $user, Lesson $lesson): bool
    {
        $entry = $this->waitlist->findActiveForUserAndLesson($user, $lesson);
        if ($entry === null) {
            return false;
        }

        $entry->cancel(Clock::get()->now());
        $this->em->flush();

        return true;
    }

    public function activeEntryFor(User $user, Lesson $lesson): ?WaitlistEntry
    {
        return $this->waitlist->findActiveForUserAndLesson($user, $lesson);
    }

    /**
     * @return list<WaitlistEntry>
     */
    public function activeEntriesFor(User $user): array
    {
        return $this->waitlist->findActiveForUser($user);
    }

    /** 1-based queue position among the lesson's active entries. */
    public function positionOf(WaitlistEntry $entry, Lesson $lesson): int
    {
        $position = 0;
        foreach ($this->waitlist->findActiveForLesson($lesson) as $candidate) {
            ++$position;
            if ($candidate->getId()->equals($entry->getId())) {
                return $position;
            }
        }

        return $position;
    }

    /**
     * Promote up to (free seats − seats already on offer) waiting entries for
     * $lesson to `offered`, notify each, and flush. Returns how many were offered.
     *
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function offerSeats(Lesson $lesson): int
    {
        $slots = $lesson->getAvailableSpots() - $this->waitlist->countActiveOffers($lesson);
        if ($slots <= 0) {
            return 0;
        }

        $now = Clock::get()->now();
        $offered = 0;
        foreach ($this->waitlist->findActiveForLesson($lesson) as $entry) {
            if ($offered >= $slots) {
                break;
            }
            if ($entry->getStatus() !== WaitlistEntry::STATUS_WAITING) {
                continue;
            }

            $entry->offer($now);
            $this->mailer->notifyOffer($entry);
            $entry->markNotified($now);
            ++$offered;
        }

        if ($offered > 0) {
            $this->em->flush();
        }

        return $offered;
    }

    /**
     * Expire every `offered` entry whose hold has elapsed at $now and flush.
     *
     * @return list<Ulid> distinct lesson ids that now have a seat to re-offer
     */
    public function expireStaleOffers(\DateTimeImmutable $now): array
    {
        $expired = $this->waitlist->findExpiredOffers($now);
        if ($expired === []) {
            return [];
        }

        /** @var array<string, Ulid> $lessonIds */
        $lessonIds = [];
        foreach ($expired as $entry) {
            $entry->expire($now);
            $lessonId = $entry->getLesson()->getId();
            $lessonIds[$lessonId->toRfc4122()] = $lessonId;
        }
        $this->em->flush();

        return array_values($lessonIds);
    }
}

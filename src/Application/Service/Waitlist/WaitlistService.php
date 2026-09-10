<?php

declare(strict_types=1);

namespace App\Application\Service\Waitlist;

use App\Application\Repository\WaitlistEntryRepositoryInterface;
use App\Entity\Lesson;
use App\Entity\WaitlistEntry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

/**
 * Queue mechanics for lesson waitlists: promote waiting entries to held offers
 * when seats are free, and expire stale offers. Notification is delegated to
 * {@see WaitlistOfferMailer}. Feature-flag / per-lesson gating is the caller's
 * responsibility (the message handlers).
 */
final readonly class WaitlistService
{
    public function __construct(
        private WaitlistEntryRepositoryInterface $waitlist,
        private EntityManagerInterface $em,
        private WaitlistOfferMailer $mailer,
    ) {}

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

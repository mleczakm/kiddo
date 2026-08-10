<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class BookingsComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * Resolves the plain user id stored on a cancelled/rescheduled lesson
     * entry (see CancelledLesson/RescheduledLesson) back to a display name.
     */
    public function resolveUserName(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return $this->userRepository->find($userId)?->getName();
    }

    #[LiveProp(writable: true, url: true)]
    public string $activeTab = 'active';

    #[LiveProp(writable: true)]
    public string $viewMode = 'list';

    /**
     * Every tab (active/past/cancelled) is really a per-*lesson* view, not a
     * per-*booking* one: a single carnet booking can have some lessons that
     * are upcoming, some already past, and some cancelled/rescheduled, all
     * at once, independent of the booking's own coarse status. So this
     * fetches every booking the user could plausibly want to see across all
     * three tabs (i.e. everything except a booking that never got past
     * PENDING/waiting-approval), and leaves the per-lesson tab filtering
     * entirely to the template — which already needs to inspect each lesson
     * individually to decide whether it's cancelled/rescheduled/past/future.
     *
     * Deliberately does NOT filter by lesson date at the SQL level: doing so
     * would make Doctrine partially hydrate `booking.lessons` from just the
     * joined+filtered rows, which is unreliable for a collection that's
     * meant to represent the *complete* set of lessons on the booking.
     *
     * @return list<Booking>
     */
    public function getCurrentBookings(): array
    {
        $user = $this->getUser();

        if (! $user) {
            return [];
        }

        /** @var list<Booking> $result */
        $result = $this->bookingRepository->createQueryBuilder('b')
            ->select('b', 'l', 's', 'p')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('l.series', 's')
            ->leftJoin('b.payment', 'p')
            ->where('b.user = :user')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                Booking::STATUS_PENDING,
                Booking::STATUS_ACTIVE,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_COMPLETED,
            ])
            ->orderBy('l.schedule', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    #[LiveAction]
    public function selectTab(#[LiveArg] string $tab): void
    {
        if (in_array($tab, ['active', 'past', 'cancelled'], true)) {
            $this->activeTab = $tab;
        }
    }
}

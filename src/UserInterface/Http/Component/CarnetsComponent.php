<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Repository\SubscriptionRepositoryInterface;
use App\Entity\Booking;
use App\Entity\Subscription;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class CarnetsComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
    ) {}

    /**
     * Active monthly subscriptions - shown alongside carnets.
     *
     * @return list<Subscription>
     *
     * @throws \LogicException
     */
    public function getSubscriptions(): array
    {
        $user = $this->getUser();

        return $user instanceof User ? $this->subscriptionRepository->findActiveForUser($user) : [];
    }

    /**
     * @return list<Booking>
     */
    #[LiveAction]
    public function getCarnets(): array
    {
        $user = $this->getUser();
        if (!$user) {
            return [];
        }

        /** @var list<Booking> $bookings */
        $bookings = $this->bookingRepository
            ->createQueryBuilder('b')
            ->select('b', 'l', 's')
            ->leftJoin('b.lessons', 'l')
            ->leftJoin('l.series', 's')
            ->where('b.user = :user')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('statuses', [Booking::STATUS_CONFIRMED])
            ->setParameter('user', $user)
            ->orderBy('l.schedule', 'ASC')
            ->getQuery()
            ->getResult();

        $carnets = [];

        foreach ($bookings as $booking) {
            if ($booking->getLessonsMap()->lessons->count() <= 1) {
                continue;
            }

            $carnets[] = $booking;
        }

        return array_reverse($carnets);
    }
}

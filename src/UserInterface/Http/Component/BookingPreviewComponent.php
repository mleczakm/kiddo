<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class BookingPreviewComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?Lesson $lesson = null;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
    ) {}

    /**
     * @return array<Booking>
     */
    public function getBookings(): array
    {
        if ($this->lesson === null) {
            return [];
        }

        /** @var ?User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return $this->bookingRepository->findForUserAndLesson($user, $this->lesson);
    }
}

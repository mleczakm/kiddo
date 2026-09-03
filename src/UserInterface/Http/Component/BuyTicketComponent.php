<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\Infrastructure\Doctrine\Repository\LessonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Panel "Kup wejście": the upcoming, bookable lessons, each opening the full
 * LessonModal (workshop preview + ticket-type choice incl. the monthly
 * subscription + participant + payment) - the same ordering flow as the public
 * workshop page, surfaced inside the panel.
 */
#[AsLiveComponent]
final class BuyTicketComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public int $weeks = 4;

    /** @var list<int> */
    public array $rangeOptions = [2, 4, 8];

    public function __construct(
        private readonly LessonRepository $lessonRepository,
    ) {}

    /**
     * @return list<Lesson>
     */
    public function getLessons(): array
    {
        $weeks = in_array($this->weeks, $this->rangeOptions, true) ? $this->weeks : 4;
        $now = Clock::get()->now();

        return array_values(array_filter(
            $this->lessonRepository->findUpcomingInRange($now, $now->modify(sprintf('+%d weeks', $weeks))),
            static fn(Lesson $lesson): bool => $lesson->canBeBooked(),
        ));
    }
}

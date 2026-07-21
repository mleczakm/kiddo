<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\Repository\LessonRepository;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('UpcomingLessons')]
class UpcomingLessons
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public ?string $query = null;

    #[LiveProp(writable: true, url: true)]
    public ?int $age = null;

    #[LiveProp(writable: true, url: true)]
    public string $week;

    #[LiveProp]
    public bool $showSearch = true;

    #[LiveProp]
    public ?int $limit = null;

    #[LiveProp]
    public ?string $openSlug = null;

    #[LiveProp]
    public ?string $openDate = null;

    #[LiveProp]
    public ?string $openHour = null;

    public function __construct(
        private LessonRepository $lessonRepository
    ) {
        $this->week = Clock::get()->now()->format('Y-m-d');
    }

    /**
     * @return Lesson[]
     */
    public function getWorkshops(): array
    {
        return $this->lessonRepository->findByFilters($this->query, $this->age, $this->week, $this->limit);
    }

    public function shouldOpenModal(Lesson $lesson): bool
    {
        if ($this->openSlug === null || $this->openSlug === '') {
            return false;
        }

        $metadata = $lesson->getMetadata();
        if ($metadata->slug !== $this->openSlug) {
            return false;
        }

        if ($this->openDate === null || $this->openHour === null) {
            return true;
        }

        return $metadata->schedule->format('Y-m-d') === $this->openDate
            && $metadata->schedule->format('H:i') === $this->openHour;
    }

    public function getCurrentWeek(): string
    {
        return Clock::get()->now()->format('Y-m-d');
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->week);
    }

    public function getWeekEnd(): \DateTimeImmutable
    {
        return $this->getWeekStart()
            ->modify('+7 days');
    }
}

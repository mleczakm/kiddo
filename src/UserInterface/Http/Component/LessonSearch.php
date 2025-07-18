<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use App\Repository\LessonRepository;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('LessonSearch')]
class LessonSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public ?string $query = null;

    #[LiveProp(writable: true, url: true)]
    public ?int $age = null;

    #[LiveProp(writable: true, url: true)]
    public string $week;

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
        return $this->lessonRepository->findByFilters($this->query, $this->age, $this->week);
    }

    public function getCurrentWeek(): string
    {
        return Clock::get()->now()->format('Y-m-d');
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->week)
            ->modify('monday this week');
    }

    public function getWeekEnd(): \DateTimeImmutable
    {
        return $this->getWeekStart()
            ->modify('sunday this week');
    }
}

<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\Component\Uid\Ulid;
use App\Entity\Series;
use App\Repository\SeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminScheduleComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly SeriesRepository $seriesRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[LiveProp(writable: true, url: true)]
    public string $week;

    #[LiveProp(writable: true, url: true)]
    public bool $showCancelled = false;

    #[LiveProp(writable: true)]
    public ?string $editSeriesId = null;

    public function mount(): void
    {
        $this->week ??= Clock::get()->now()->format('Y-m-d');
    }

    /**
     * @return list<Series>
     */
    public function getSeries(): array
    {
        $start = new \DateTimeImmutable($this->week);
        $end = $start->modify('+7 days 23:59:59');
        /** @var list<Series> $result */
        $result = $this->seriesRepository->findInRange($start, $end, $this->showCancelled);
        return $result;
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

    /**
     * @return list<string>
     */
    public function getTicketTypes(Series $series): array
    {
        $types = [];
        foreach ($series->ticketOptions as $opt) {
            $types[] = $opt->type->value;
        }
        return $types;
    }

    #[LiveAction]
    public function startEdit(#[LiveArg] string $seriesId): void
    {
        $this->editSeriesId = $seriesId;
    }

    #[LiveAction]
    public function cancelEdit(): void
    {
        $this->editSeriesId = null;
    }

    #[LiveAction]
    public function toggleCancelled(): void
    {
        $this->showCancelled = ! $this->showCancelled;
    }

    #[LiveAction]
    public function cancelSeries(#[LiveArg] string $seriesId): void
    {
        $id = Ulid::fromString($seriesId);
        $series = $this->seriesRepository->find($id);
        if ($series instanceof Series) {
            $series->cancel();
            $this->em->flush();
        }
    }

    #[LiveAction]
    public function activateSeries(#[LiveArg] string $seriesId): void
    {
        $id = Ulid::fromString($seriesId);
        $series = $this->seriesRepository->find($id);
        if ($series instanceof Series) {
            $series->activate();
            $this->em->flush();
        }
    }
}

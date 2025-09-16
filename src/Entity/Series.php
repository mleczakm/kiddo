<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeriesRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SeriesRepository::class)]
class Series
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /**
     * @param Collection<int, Lesson> $lessons
     * @param list<TicketOption> $ticketOptions
     */
    public function __construct(
        #[ORM\OneToMany(targetEntity: Lesson::class, mappedBy: 'series')]
        public Collection $lessons,
        #[ORM\Column(type: 'string', enumType: WorkshopType::class)]
        public WorkshopType $type = WorkshopType::WEEKLY,
        #[ORM\Column(type: 'json_document', options: [
            'jsonb' => true,
            'default' => '[]',
        ])]
        public array $ticketOptions = [],
        #[ORM\Column(type: 'string', options: [
            'default' => 'active',
        ])]
        public string $status = 'active',
    ) {
        $this->id = new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    /**
     * @return Lesson[]
     */
    public function getLessonsGte(Lesson $lesson, int $limit): array
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->gte('metadata.schedule', $lesson->getMetadata()->schedule))
            ->setMaxResults($limit)
            ->orderBy([
                'id' => 'ASC',
            ]);

        return $this->lessons->matching($criteria)
            ->toArray();
    }

    public function getLessonsGt(Lesson $lesson): ?Lesson
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->gt('metadata.schedule', $lesson->getMetadata()->schedule))
            ->setMaxResults(1)
            ->orderBy([
                'id' => 'ASC',
            ]);

        return $this->lessons->matching($criteria)
            ->first() ?: null;
    }

    public function getLessonsLt(Lesson $lesson): ?Lesson
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->lt('metadata.schedule', $lesson->getMetadata()->schedule))
            ->setMaxResults(1)
            ->orderBy([
                'id' => 'DESC',
            ]);

        return $this->lessons->matching($criteria)
            ->first() ?: null;
    }

    public function getFirstLesson(): Lesson
    {
        if ($this->lessons->isEmpty()) {
            throw new \LogicException('No lessons found');
        }

        /** @var ?Lesson $first */
        $first = null;
        foreach ($this->lessons as $lesson) {
            if ($first === null || $lesson->getMetadata()->schedule < $first->getMetadata()->schedule) {
                $first = $lesson;
            }
        }

        if ($first === null) {
            throw new \LogicException('No lessons found');
        }

        return $first;
    }

    public function getLastLesson(): Lesson
    {
        if ($this->lessons->isEmpty()) {
            throw new \LogicException('No lessons found');
        }

        /** @var ?Lesson $last */
        $last = null;
        foreach ($this->lessons as $lesson) {
            if ($last === null || $lesson->getMetadata()->schedule > $last->getMetadata()->schedule) {
                $last = $lesson;
            }
        }

        if ($last === null) {
            throw new \LogicException('No lessons found');
        }

        return $last;
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
    }

    public function activate(): void
    {
        $this->status = 'active';
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
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
        /**
         * @var Collection<int, User>
         */
        #[ORM\ManyToMany(targetEntity: User::class)]
        #[ORM\JoinTable(name: 'series_instructor')]
        public Collection $instructors = new ArrayCollection(),
        /**
         * When set, ExtendSeriesScheduleHandler stops generating new Lesson
         * occurrences for this series once it reaches this date — existing
         * occurrences (including ones after this date, if already created)
         * are left untouched. This is the supported way to wind a recurring
         * series down on a known date.
         */
        #[ORM\Column(type: 'date_immutable', nullable: true)]
        public ?\DateTimeImmutable $lastOccurrenceDate = null,
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
            ->where(Criteria::expr()->gte('schedule', $lesson->schedule))
            ->setMaxResults($limit)
            ->orderBy([
                'id' => Order::Ascending,
            ]);

        return $this->lessons->matching($criteria)->toArray();
    }

    public function getLessonsGt(Lesson $lesson): ?Lesson
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->gt('schedule', $lesson->schedule))
            ->setMaxResults(1)
            ->orderBy([
                'id' => Order::Ascending,
            ]);

        return $this->lessons->matching($criteria)->first() ?: null;
    }

    public function getLessonsLt(Lesson $lesson): ?Lesson
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->lt('schedule', $lesson->schedule))
            ->setMaxResults(1)
            ->orderBy([
                'id' => Order::Descending,
            ]);

        return $this->lessons->matching($criteria)->first() ?: null;
    }

    public function getFirstLesson(): Lesson
    {
        if ($this->lessons->isEmpty()) {
            throw new \LogicException('No lessons found');
        }

        /** @var ?Lesson $first */
        $first = null;
        foreach ($this->lessons as $lesson) {
            if ($first === null || $lesson->schedule < $first->schedule) {
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
            if ($last === null || $lesson->schedule > $last->schedule) {
                $last = $lesson;
            }
        }

        if ($last === null) {
            throw new \LogicException('No lessons found');
        }

        return $last;
    }

    /**
     * @return Collection<int, User>
     */
    public function getInstructors(): Collection
    {
        return $this->instructors;
    }

    public function addInstructor(User $instructor): self
    {
        if (!$this->instructors->contains($instructor)) {
            $this->instructors->add($instructor);
        }

        return $this;
    }

    public function removeInstructor(User $instructor): self
    {
        $this->instructors->removeElement($instructor);

        return $this;
    }
}

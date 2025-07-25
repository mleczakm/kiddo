<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\Criteria;
use Brick\Money\Money;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Series
{
    /**
     * @var list<TicketOption>
     */
    #[ORM\Column(type: 'json_document', options: [
        'jsonb' => true,
    ])]
    public array $ticketOptions = [];

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
        array $ticketOptions = [],
    ) {
        $this->id = new Ulid();
        /** @var list<TicketOption> $ticketOptions */
        $this->ticketOptions = $ticketOptions ?: [new TicketOption(TicketType::CARNET_4, Money::of(180, 'PLN'))];
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
}

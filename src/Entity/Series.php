<?php

declare(strict_types=1);

namespace App\Entity;

use Brick\Money\Money;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Series
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /**
     * @var Collection<int, Lesson>
     */
    #[ORM\OneToMany(mappedBy: 'series', targetEntity: Lesson::class)]
    public Collection $lessons;

    #[ORM\Column(type: 'string', enumType: WorkshopType::class)]
    public WorkshopType $type = WorkshopType::WEEKLY;

    /**
     * @var list<TicketOption>
     */
    #[ORM\Column(type: 'json_document', options: [
        'jsonb' => true,
    ])]
    public array $ticketOptions = [];

    /**
     * @param Collection<int, Lesson> $lessons
     * @param list<TicketOption> $ticketOptions
     */
    public function __construct(
        Collection $lessons,
        WorkshopType $type = WorkshopType::WEEKLY,
        array $ticketOptions = [],
    ) {
        $this->id = new Ulid();
        $this->lessons = $lessons;
        $this->type = $type;
        /** @var list<TicketOption> $ticketOptions */
        $this->ticketOptions = $ticketOptions ?: [new TicketOption(TicketType::CARNET_4, Money::of(180, 'PLN'))];
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    /**
     * @return list<Reservation>
     */
    public function apply(Ticket $ticket): array
    {
        $reservations = [];

        foreach ($this->findActiveLessons() as $lesson) {
            if ($ticket->match($lesson)) {
                array_push($reservations, ...$lesson->apply($ticket));
            }
        }

        return $reservations;
    }

    /**
     * @return iterable<Lesson>
     */
    private function findActiveLessons(): iterable
    {
        yield from $this->lessons;
    }
}

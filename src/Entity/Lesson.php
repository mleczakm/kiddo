<?php

declare(strict_types=1);

namespace App\Entity;

use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Lesson
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'json_document', options: [
        'jsonb' => true,
    ])]
    private LessonMetadata $metadata;

    #[ORM\Column(type: 'string', nullable: false)]
    public string $status;

    public WorkshopType $type = WorkshopType::WEEKLY;

    private array $ticketOptions;

    private ?Series $series = null;

    public function __construct(LessonMetadata $metadata)
    {
        $this->id = new Ulid();
        $this->metadata = $metadata;
        $this->ticketOptions = [new TicketOption(TicketType::ONE_TIME, Money::of(50, 'PLN'))];
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getMetadata(): LessonMetadata
    {
        return $this->metadata;
    }

    public function setMetadata(LessonMetadata $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * @return list<Reservation>
     */
    public function apply(Ticket $ticket): array
    {
        $reservation = new Reservation($this, $ticket, Clock::get()->now());
        $ticket->addReservation($reservation);

        return [$reservation];
    }

    public function getTicketOptions(): iterable
    {
        if ($this->series) {
            yield from $this->series->ticketOptions;
        }
        yield from $this->ticketOptions;
    }

    public function setSeries(Series $series): Lesson
    {
        $this->series = $series;

        return $this;
    }

    public function defaultTicketOption(): TicketOption
    {
        return $this->ticketOptions[0];
    }
}

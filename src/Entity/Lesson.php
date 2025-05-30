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
    #[ORM\Column(type: 'string', nullable: false)]
    public string $status;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /**
     * @var list<TicketOption>
     */
    #[ORM\Column(type: 'json_document', options: [
        'jsonb' => true,
    ])]
    private array $ticketOptions;

    #[ORM\ManyToOne(targetEntity: Series::class, inversedBy: 'lessons')]
    private ?Series $series = null;

    public function __construct(
        #[ORM\Column(type: 'json_document', options: [
            'jsonb' => true,
        ])]
        private LessonMetadata $metadata
    ) {
        $this->id = new Ulid();
        $this->status = 'active';
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

    /**
     * @return array<TicketOption>
     */
    public function getTicketOptions(): array
    {
        $options = [];
        if ($this->series) {
            $options = array_merge($options, $this->series->ticketOptions);
        }
        $options = array_merge($options, $this->ticketOptions);

        return $options;
    }

    public function getMatchingTicketOption(string $selectedTicketType): TicketOption
    {
        foreach ($this->getTicketOptions() as $option) {
            if ($option->type->value === $selectedTicketType) {
                return $option;
            }
        }

        throw new \InvalidArgumentException('Unsupported ticket type: ' . $selectedTicketType);
    }

    public function setSeries(Series $series): self
    {
        $this->series = $series;

        return $this;
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function defaultTicketOption(): TicketOption
    {
        return $this->ticketOptions[0];
    }
}

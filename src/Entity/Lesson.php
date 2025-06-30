<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Lesson
{
    #[ORM\Column(type: 'string', nullable: false)]
    public string $status;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: Series::class, inversedBy: 'lessons')]
    private ?Series $series = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\ManyToMany(targetEntity: Booking::class, mappedBy: 'lessons')]
    private Collection $bookings;

    public function __construct(
        #[ORM\Embedded(class: LessonMetadata::class, columnPrefix: false)]
        private LessonMetadata $metadata,
        /**
         * @var list<TicketOption>
         */
        #[ORM\Column(type: 'json_document', options: [
            'jsonb' => true,
        ])]
        private array $ticketOptions = [],
    ) {
        $this->id = new Ulid();
        $this->status = 'active';
        $this->bookings = new ArrayCollection();
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

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (! $this->bookings->contains($booking)) {
            $this->bookings[] = $booking;
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        $this->bookings->removeElement($booking);
        return $this;
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function defaultTicketOption(): ?TicketOption
    {
        return $this->series?->ticketOptions[0] ?? $this->ticketOptions[0] ?? null;
    }

    public function getAvailableSpots(): int
    {
        return max(
            0,
            $this->metadata->capacity -
            $this->bookings->filter(fn(Booking $booking): bool => $booking->isConfirmed())
                ->count()
        );
    }
}

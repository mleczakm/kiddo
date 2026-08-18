<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: LessonRepository::class)]
class Lesson
{
    #[ORM\Column(type: 'string', nullable: false, options: [
        'default' => 'active',
    ])]
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

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'lesson_instructor')]
    private Collection $instructors;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: LessonMetadata::class, cascade: ['persist'])]
        #[ORM\JoinColumn(nullable: false)]
        private LessonMetadata $metadata,
        /**
         * This occurrence's own date/time — the one piece of content that must
         * always be unique per Lesson, so (unlike the rest of a workshop's
         * content) it can never live on the shared LessonMetadata.
         */
        #[ORM\Column(type: 'datetime_immutable')]
        public \DateTimeImmutable $schedule,
        /**
         * @var list<TicketOption>
         */
        #[ORM\Column(type: 'json_document', options: [
            'jsonb' => true,
            'default' => '[]',
        ])]
        private array $ticketOptions = [],
    ) {
        $this->id = new Ulid();
        $this->status = 'active';
        $this->bookings = new ArrayCollection();
        $this->instructors = new ArrayCollection();
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
        // Keyed by TicketType so a per-occurrence override replaces the
        // series-level option of the same type instead of duplicating it.
        $options = [];
        if ($this->series) {
            foreach ($this->series->ticketOptions as $option) {
                $options[$option->type->value] = $option;
            }
        }
        foreach ($this->ticketOptions as $option) {
            $options[$option->type->value] = $option;
        }

        return array_values($options);
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
        $this->series->lessons->add($this);

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
        if (!$this->bookings->contains($booking)) {
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
            $this->metadata->capacity
            - $this->bookings
                ->filter(
                    fn(Booking $booking): bool => $booking->occupiesSeat()
                    && $booking->getLessonsMap()->active->hasKey($this->id),
                )
                ->count(),
        );
    }

    public function canBeBooked(): bool
    {
        return $this->status === 'active' && $this->getAvailableSpots() > 0;
    }

    public function future(): bool
    {
        return $this->schedule >= Clock::get()->now();
    }

    /**
     * @return User[]
     */
    public function getAttendants(): array
    {
        return $this->bookings
            ->filter(static fn(Booking $booking): bool => $booking->isConfirmed())
            ->map(static fn(Booking $booking): User => $booking->getUser())
            ->toArray();
    }

    public function cancellationAvailable(): bool
    {
        return $this->status === 'active' && $this->schedule >= Clock::get()->now()->modify('+24 hours');
    }

    public function setTicketOptions(TicketOption ...$options): void
    {
        $this->ticketOptions = array_values($options);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
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

    /**
     * Get all instructors (from lesson itself and from series)
     * @return User[]
     */
    public function getAllInstructors(): array
    {
        $instructors = $this->instructors->toArray();
        if ($this->series) {
            $instructors = array_merge($instructors, $this->series->getInstructors()->toArray());
        }

        // Remove duplicates
        $uniqueInstructors = [];
        $seen = [];
        foreach ($instructors as $instructor) {
            $id = $instructor->getId();
            if (!in_array($id, $seen, true)) {
                $seen[] = $id;
                $uniqueInstructors[] = $instructor;
            }
        }

        return $uniqueInstructors;
    }
}

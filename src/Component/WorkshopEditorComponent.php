<?php

declare(strict_types=1);

namespace App\Component;

use Doctrine\Common\Collections\ArrayCollection;
use Brick\Money\Money;
use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\User;
use App\Entity\WorkshopType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('WorkshopEditor', template: 'components/WorkshopEditorComponent.html.twig')]
class WorkshopEditorComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public bool $isModalOpen = false;

    #[LiveProp]
    public ?Ulid $editingSeriesId = null;

    #[LiveProp(writable: true)]
    public string $activeTab = 'general';

    // General tab fields
    #[LiveProp(writable: true)]
    public ?string $title = null;

    #[LiveProp(writable: true)]
    public ?string $category = null;

    #[LiveProp(writable: true)]
    public ?string $description = null;

    #[LiveProp(writable: true)]
    public ?string $lead = null;

    #[LiveProp(writable: true)]
    public ?string $visualTheme = null;

    #[LiveProp(writable: true)]
    public ?int $ageMin = null;

    #[LiveProp(writable: true)]
    public ?int $ageMax = null;

    #[LiveProp(writable: true)]
    public ?int $capacity = null;

    #[LiveProp(writable: true)]
    public ?int $duration = null;

    // Schedule tab fields
    #[LiveProp(writable: true)]
    public string $scheduleType = 'recurring';

    #[LiveProp(writable: true)]
    public ?string $dayOfWeek = null;

    #[LiveProp(writable: true)]
    public ?string $startTime = null;

    #[LiveProp(writable: true)]
    public ?string $endTime = null;

    #[LiveProp(writable: true)]
    public ?\DateTimeImmutable $startDate = null;

    #[LiveProp(writable: true)]
    public ?\DateTimeImmutable $endDate = null;

    #[LiveProp(writable: true)]
    public bool $skipHolidays = true;

    // Tickets tab fields
    #[LiveProp(writable: true)]
    public bool $allowPayOnPlace = false;

    #[LiveProp(writable: true)]
    public ?string $singleTicketPrice = null;

    #[LiveProp(writable: true)]
    public ?string $carnet4Price = null;

    // Instructors
    /**
     * @var array<int, string>
     */
    #[LiveProp(writable: true)]
    public array $instructorIds = [];

    #[LiveProp(writable: true)]
    public ?string $newInstructorId = null;

    private ?Series $editingSeries = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function mount(?Ulid $seriesId = null): void
    {
        if ($seriesId !== null) {
            $this->editingSeriesId = $seriesId;
            $this->loadSeriesData();
        }
        $this->isModalOpen = true;
    }

    private function loadSeriesData(): void
    {
        if ($this->editingSeriesId === null) {
            return;
        }

        $this->editingSeries = $this->entityManager->find(Series::class, $this->editingSeriesId);
        if ($this->editingSeries === null) {
            return;
        }

        $firstLesson = $this->editingSeries->getFirstLesson();
        $metadata = $firstLesson->getMetadata();

        $this->title = $metadata->title;
        $this->category = $metadata->category;
        $this->description = $metadata->description;
        $this->lead = $metadata->lead;
        $this->visualTheme = $metadata->visualTheme;
        $this->ageMin = $metadata->ageRange->min;
        $this->ageMax = $metadata->ageRange->max;
        $this->capacity = $metadata->capacity;
        $this->duration = $metadata->duration;

        // Load instructors
        $this->instructorIds = array_map(
            fn(User $u) => (string) $u->getId(),
            $this->editingSeries->getInstructors()
                ->toArray()
        );

        // Load ticket options
        foreach ($this->editingSeries->ticketOptions as $option) {
            if ($option->type === TicketType::ONE_TIME) {
                $this->singleTicketPrice = (string) $option->price->getMinorAmount();
            } elseif ($option->type === TicketType::CARNET_4) {
                $this->carnet4Price = (string) $option->price->getMinorAmount();
            }
        }
    }

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->userRepository->findAll();
    }

    /**
     * @return User[]
     */
    public function getInstructors(): array
    {
        if (empty($this->instructorIds)) {
            return [];
        }

        return $this->userRepository->findBy([
            'id' => $this->instructorIds,
        ]);
    }

    public function getCategories(): array
    {
        return ['Sensoryka', 'Muzyka', 'Ruchowe', 'Plastyczne', 'Brudna zabawa'];
    }

    public function getDaysOfWeek(): array
    {
        return [
            'Monday' => 'Poniedziałek',
            'Tuesday' => 'Wtorek',
            'Wednesday' => 'Środa',
            'Thursday' => 'Czwartek',
            'Friday' => 'Piątek',
            'Saturday' => 'Sobota',
            'Sunday' => 'Niedziela',
        ];
    }

    #[LiveAction]
    public function addInstructor(): void
    {
        if ($this->newInstructorId === null) {
            return;
        }

        if (! in_array($this->newInstructorId, $this->instructorIds, true)) {
            $this->instructorIds[] = $this->newInstructorId;
        }

        $this->newInstructorId = null;
    }

    #[LiveAction]
    public function removeInstructor(string $userId): void
    {
        $this->instructorIds = array_filter($this->instructorIds, fn(string $id) => $id !== $userId);
    }

    #[LiveAction]
    public function save(): void
    {
        // Validate required fields
        if ($this->title === null || $this->category === null || $this->description === null) {
            $this->addFlash('error', 'Wypełnij wszystkie wymagane pola.');
            return;
        }

        // Create or update series
        if ($this->editingSeriesId !== null) {
            $series = $this->entityManager->find(Series::class, $this->editingSeriesId);
            if ($series === null) {
                $this->addFlash('error', 'Series not found.');
                return;
            }
        } else {
            $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
            $this->entityManager->persist($series);
        }

        // Update instructors
        $series->instructors->clear();
        foreach ($this->instructorIds as $userId) {
            $user = $this->userRepository->find((int) $userId);
            if ($user !== null) {
                $series->addInstructor($user);
            }
        }

        // Create ticket options
        $ticketOptions = [];
        if ($this->singleTicketPrice !== null) {
            $ticketOptions[] = new TicketOption(
                TicketType::ONE_TIME,
                Money::ofMinor($this->singleTicketPrice, 'PLN'),
                'Wejście jednorazowe',
                TicketReschedulePolicy::UNLIMITED_24H_BEFORE,
            );
        }
        if ($this->carnet4Price !== null) {
            $ticketOptions[] = new TicketOption(
                TicketType::CARNET_4,
                Money::ofMinor($this->carnet4Price, 'PLN'),
                'Karnet: 4 wejścia',
                TicketReschedulePolicy::ONETIME_24H_BEFORE,
            );
        }
        $series->ticketOptions = $ticketOptions;

        // For now, we'll create a single lesson as a placeholder
        // In a real implementation, you'd generate multiple lessons based on the schedule
        $metadata = new LessonMetadata(
            title: $this->title,
            lead: $this->lead ?? '',
            visualTheme: $this->visualTheme ?? 'default',
            description: $this->description,
            capacity: $this->capacity ?? 10,
            schedule: $this->startDate ?? Clock::get()->now(),
            duration: $this->duration ?? 90,
            ageRange: new AgeRange($this->ageMin ?? 0, $this->ageMax ?? 10),
            category: $this->category,
        );

        $lesson = new Lesson($metadata);
        $lesson->setSeries($series);

        // Add instructors to lesson as well
        foreach ($this->instructorIds as $userId) {
            $user = $this->userRepository->find((int) $userId);
            if ($user !== null) {
                $lesson->addInstructor($user);
            }
        }

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $this->addFlash('success', 'Warsztat został zapisany pomyślnie.');
        $this->isModalOpen = false;
        $this->emitUp('workshopEditorSaved');
    }
}

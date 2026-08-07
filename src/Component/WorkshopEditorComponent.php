<?php

declare(strict_types=1);

namespace App\Component;

use App\Application\Service\InAppNotificationService;
use App\Application\Service\LessonInstructorResolver;
use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\NotificationSeverity;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\User;
use App\Entity\WorkshopType;
use App\Repository\UserRepository;
use App\Security\Voter\LessonVoter;
use Brick\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Create-a-new-series (admin only, via AdminScheduleComponent's "Dodaj
 * zajęcia") and edit-an-existing-lesson (admins + assigned instructors, via
 * the "Modyfikuj" trigger on a lesson's own page) share this one editor.
 *
 * Editing always targets a specific Lesson (`editingLessonId`). When that
 * lesson belongs to a Series, `editScope` decides how far the change
 * reaches:
 *  - 'occurrence' (default): only this Lesson's own metadata/ticket
 *    options/instructors change.
 *  - 'series': the same content fields (never the date/time — each
 *    occurrence keeps its own) propagate to every other still-upcoming,
 *    active Lesson in the series, and ticket options/instructors move to
 *    the Series itself so newly-generated future occurrences inherit them
 *    too.
 */
#[AsLiveComponent('WorkshopEditor', template: 'components/WorkshopEditorComponent.html.twig')]
class WorkshopEditorComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true)]
    public bool $isModalOpen = false;

    #[LiveProp]
    public ?Ulid $editingSeriesId = null;

    #[LiveProp]
    public ?Ulid $editingLessonId = null;

    #[LiveProp(writable: true)]
    public string $activeTab = 'general';

    /**
     * 'occurrence' or 'series' — only meaningful when editingLessonId is set
     * and that lesson belongs to a Series.
     */
    #[LiveProp(writable: true)]
    public string $editScope = 'occurrence';

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

    // This occurrence's own date/time — only used when editing an existing lesson.
    #[LiveProp(writable: true)]
    public ?string $occurrenceDate = null;

    #[LiveProp(writable: true)]
    public ?string $occurrenceTime = null;

    // Schedule tab fields — only used when creating a brand-new series.
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
    public ?string $instructorSearch = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InAppNotificationService $inAppNotifications,
        private readonly LessonInstructorResolver $instructorResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {}

    public function mount(?Ulid $seriesId = null, ?Ulid $lessonId = null, bool $startOpen = true): void
    {
        if ($lessonId !== null) {
            $this->editingLessonId = $lessonId;
            $this->loadLessonData();
        } elseif ($seriesId !== null) {
            $this->editingSeriesId = $seriesId;
            $this->loadSeriesRepresentativeLesson();
        }
        $this->isModalOpen = $startOpen;
    }

    /**
     * Legacy entry point (AdminScheduleComponent's series-level "Edytuj"):
     * pick a representative lesson for that series — the next upcoming
     * active one, falling back to the first lesson — and edit through the
     * same single-lesson path, defaulting to series-wide scope since the
     * admin clicked "edit" on the series row, not a specific date.
     */
    private function loadSeriesRepresentativeLesson(): void
    {
        if ($this->editingSeriesId === null) {
            return;
        }

        $series = $this->entityManager->find(Series::class, $this->editingSeriesId);
        if ($series === null) {
            return;
        }

        $now = Clock::get()->now();
        $representative = null;
        foreach ($series->lessons as $candidate) {
            if ($candidate->status !== 'active' || $candidate->getMetadata()->schedule < $now) {
                continue;
            }
            if ($representative === null || $candidate->getMetadata()->schedule < $representative->getMetadata()->schedule) {
                $representative = $candidate;
            }
        }
        $representative ??= $series->getFirstLesson();

        $this->editingLessonId = $representative->getId();
        $this->editScope = 'series';
        $this->loadLessonData();
    }

    private function loadLessonData(): void
    {
        if ($this->editingLessonId === null) {
            return;
        }

        $lesson = $this->entityManager->find(Lesson::class, $this->editingLessonId);
        if ($lesson === null) {
            return;
        }

        $series = $lesson->getSeries();
        if ($series !== null) {
            $this->editingSeriesId = $series->getId();
        }

        $metadata = $lesson->getMetadata();
        $this->title = $metadata->title;
        $this->category = $metadata->category;
        $this->description = $metadata->description;
        $this->lead = $metadata->lead;
        $this->visualTheme = $metadata->visualTheme;
        $this->ageMin = $metadata->ageRange->min;
        $this->ageMax = $metadata->ageRange->max;
        $this->capacity = $metadata->capacity;
        $this->duration = $metadata->duration;
        $this->occurrenceDate = $metadata->schedule->format('Y-m-d');
        $this->occurrenceTime = $metadata->schedule->format('H:i');

        $this->instructorIds = array_map(
            fn(User $u) => (string) $u->getId(),
            $lesson->getAllInstructors()
        );

        foreach ($lesson->getTicketOptions() as $option) {
            if ($option->type === TicketType::ONE_TIME) {
                $this->singleTicketPrice = (string) $option->price->getMinorAmount();
            } elseif ($option->type === TicketType::CARNET_4) {
                $this->carnet4Price = (string) $option->price->getMinorAmount();
            }
        }
    }

    /**
     * Re-queried on every call rather than cached from mount() — LiveComponent
     * rehydrates a fresh instance from #[LiveProp] values on each subsequent
     * request, so a plain property set only in mount() would silently reset
     * to null on every interaction after the first render.
     */
    public function getEditingLesson(): ?Lesson
    {
        if ($this->editingLessonId === null) {
            return null;
        }

        return $this->entityManager->find(Lesson::class, $this->editingLessonId);
    }

    public function getEditingSeries(): ?Series
    {
        return $this->getEditingLesson()?->getSeries();
    }

    /**
     * How many other lessons in the series would also be touched by
     * "cały cykl" — shown so the scope choice is never a guess.
     */
    public function getUpcomingSeriesLessonsCount(): int
    {
        $lesson = $this->getEditingLesson();
        if ($lesson === null) {
            return 0;
        }
        $series = $lesson->getSeries();
        if ($series === null) {
            return 0;
        }

        $now = Clock::get()->now();
        $count = 0;
        foreach ($series->lessons as $sibling) {
            if ($sibling === $lesson) {
                continue;
            }
            if ($sibling->status === 'active' && $sibling->getMetadata()->schedule >= $now) {
                $count++;
            }
        }

        return $count;
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

    /**
     * @return User[]
     */
    public function getFilteredInstructors(): array
    {
        if ($this->instructorSearch === null || mb_strlen(trim($this->instructorSearch)) < 2) {
            return [];
        }

        $results = $this->userRepository->findForAutocomplete($this->instructorSearch);

        return array_values(array_filter(
            $results,
            fn(User $user) => ! in_array((string) $user->getId(), $this->instructorIds, true)
        ));
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
    public function openModal(): void
    {
        $this->isModalOpen = true;
    }

    #[LiveAction]
    public function close(): void
    {
        $this->isModalOpen = false;
        $this->emitUp('workshopEditorClosed');
    }

    #[LiveAction]
    public function addInstructor(#[LiveArg] string $userId): void
    {
        if (! in_array($userId, $this->instructorIds, true)) {
            $this->instructorIds[] = $userId;
        }

        $this->instructorSearch = null;
    }

    #[LiveAction]
    public function removeInstructor(#[LiveArg] string $userId): void
    {
        $this->instructorIds = array_values(array_filter($this->instructorIds, fn(string $id) => $id !== $userId));
    }

    #[LiveAction]
    public function save(): void
    {
        if ($this->title === null || $this->category === null || $this->description === null) {
            $this->addFlash('error', 'Wypełnij wszystkie wymagane pola.');
            return;
        }

        if ($this->editingLessonId !== null) {
            $this->saveExistingLesson();
        } else {
            $this->saveNewSeries();
        }
    }

    private function saveExistingLesson(): void
    {
        $lesson = $this->entityManager->find(Lesson::class, $this->editingLessonId);
        if ($lesson === null) {
            $this->addFlash('error', 'Nie znaleziono zajęć.');
            return;
        }

        if (! $this->isGranted(LessonVoter::MANAGE, $lesson)) {
            $this->addFlash('error', 'Nie masz uprawnień do edycji tych zajęć.');
            return;
        }

        $series = $lesson->getSeries();
        $scope = ($this->editScope === 'series' && $series !== null) ? 'series' : 'occurrence';

        $scheduleString = trim(($this->occurrenceDate ?? '') . ' ' . ($this->occurrenceTime ?? ''));
        try {
            $newSchedule = new \DateTimeImmutable($scheduleString);
        } catch (\Exception) {
            $this->addFlash('error', 'Nieprawidłowa data lub godzina.');
            return;
        }

        $affectedLessons = [$lesson];
        $lesson->setMetadata($this->buildMetadataFor($lesson, $newSchedule));

        if ($scope === 'series') {
            $now = Clock::get()->now();
            foreach ($series->lessons as $sibling) {
                if ($sibling === $lesson || $sibling->status !== 'active' || $sibling->getMetadata()->schedule < $now) {
                    continue;
                }
                // Content propagates; each occurrence keeps its own date/time.
                $sibling->setMetadata($this->buildMetadataFor($sibling, $sibling->getMetadata()->schedule));
                $affectedLessons[] = $sibling;
            }
        }

        $ticketOptions = $this->buildTicketOptions();
        $instructors = $this->resolveSelectedInstructors();

        if ($scope === 'series') {
            $series->ticketOptions = $ticketOptions;
            $lesson->setTicketOptions();
            $series->instructors->clear();
            foreach ($instructors as $user) {
                $series->addInstructor($user);
            }
            $lesson->getInstructors()->clear();
        } else {
            $lesson->setTicketOptions(...$ticketOptions);
            $lesson->getInstructors()->clear();
            foreach ($instructors as $user) {
                $lesson->addInstructor($user);
            }
        }

        $this->entityManager->flush();

        $this->notifyAboutEdit($affectedLessons, $scope);

        $this->addFlash('success', 'Warsztat został zaktualizowany.');
        $this->isModalOpen = false;
        $this->emitUp('workshopEditorSaved');
    }

    private function saveNewSeries(): void
    {
        if (! $this->isGranted('ROLE_MANAGE_SCHEDULE')) {
            $this->addFlash('error', 'Nie masz uprawnień do tworzenia nowych warsztatów.');
            return;
        }

        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $this->entityManager->persist($series);

        $instructors = $this->resolveSelectedInstructors();
        foreach ($instructors as $user) {
            $series->addInstructor($user);
        }

        $series->ticketOptions = $this->buildTicketOptions();

        $metadata = new LessonMetadata(
            title: (string) $this->title,
            lead: $this->lead ?? '',
            visualTheme: $this->visualTheme ?? 'default',
            description: (string) $this->description,
            capacity: $this->capacity ?? 10,
            schedule: $this->startDate ?? Clock::get()->now(),
            duration: $this->duration ?? 90,
            ageRange: new AgeRange($this->ageMin ?? 0, $this->ageMax ?? 10),
            category: (string) $this->category,
        );

        $lesson = new Lesson($metadata);
        $lesson->setSeries($series);
        foreach ($instructors as $user) {
            $lesson->addInstructor($user);
        }

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $this->addFlash('success', 'Warsztat został zapisany pomyślnie.');
        $this->isModalOpen = false;
        $this->emitUp('workshopEditorSaved');
    }

    private function buildMetadataFor(Lesson $lesson, \DateTimeImmutable $schedule): LessonMetadata
    {
        return $lesson->getMetadata()
            ->withTitle((string) $this->title)
            ->withLead($this->lead ?? '')
            ->withVisualTheme($this->visualTheme ?? 'default')
            ->withDescription((string) $this->description)
            ->withCapacity($this->capacity ?? 10)
            ->withSchedule($schedule)
            ->withDuration($this->duration ?? 90)
            ->withAgeRange(new AgeRange($this->ageMin ?? 0, $this->ageMax ?? 10))
            ->withCategory((string) $this->category);
    }

    /**
     * @return list<TicketOption>
     */
    private function buildTicketOptions(): array
    {
        $ticketOptions = [];
        if ($this->singleTicketPrice !== null && $this->singleTicketPrice !== '') {
            $ticketOptions[] = new TicketOption(
                TicketType::ONE_TIME,
                Money::ofMinor($this->singleTicketPrice, 'PLN'),
                'Wejście jednorazowe',
                TicketReschedulePolicy::UNLIMITED_24H_BEFORE,
            );
        }
        if ($this->carnet4Price !== null && $this->carnet4Price !== '') {
            $ticketOptions[] = new TicketOption(
                TicketType::CARNET_4,
                Money::ofMinor($this->carnet4Price, 'PLN'),
                'Karnet: 4 wejścia',
                TicketReschedulePolicy::ONETIME_24H_BEFORE,
            );
        }

        return $ticketOptions;
    }

    /**
     * @return User[]
     */
    private function resolveSelectedInstructors(): array
    {
        $users = [];
        foreach ($this->instructorIds as $userId) {
            $user = $this->userRepository->find((int) $userId);
            if ($user !== null) {
                $users[] = $user;
            }
        }

        return $users;
    }

    /**
     * @param Lesson[] $affectedLessons
     */
    private function notifyAboutEdit(array $affectedLessons, string $scope): void
    {
        $editor = $this->getUser();
        if (! $editor instanceof User || $affectedLessons === []) {
            return;
        }

        $lessonTitle = $affectedLessons[0]->getMetadata()->title;
        $scopeLabel = $this->translator->trans(
            $scope === 'series' ? 'admin.workshop_editor.scope.series_short' : 'admin.workshop_editor.scope.occurrence_short',
            [],
            'messages'
        );
        $url = $this->urlGenerator->generate('app_admin_lesson_view', ['id' => (string) $affectedLessons[0]->getId()]);

        $instructors = $this->instructorResolver->resolve($affectedLessons, exclude: $editor);
        $this->inAppNotifications->notifyUsers(
            $instructors,
            $this->translator->trans('notifications.in_app.workshop_edited.instructor.title', [], 'messages'),
            $this->translator->trans('notifications.in_app.workshop_edited.instructor.body', [
                'editor' => $editor->getName(),
                'lesson' => $lessonTitle,
                'scope' => $scopeLabel,
            ], 'messages'),
            $url,
            NotificationSeverity::Info,
        );

        $instructorIds = array_map(fn(User $u) => $u->getId(), $instructors);
        $admins = array_values(array_filter(
            $this->userRepository->findByRole('ROLE_ADMIN'),
            fn(User $admin) => $admin->getId() !== $editor->getId() && ! in_array($admin->getId(), $instructorIds, true)
        ));
        $this->inAppNotifications->notifyUsers(
            $admins,
            $this->translator->trans('notifications.in_app.workshop_edited.admin.title', [], 'messages'),
            $this->translator->trans('notifications.in_app.workshop_edited.admin.body', [
                'editor' => $editor->getName(),
                'lesson' => $lessonTitle,
                'scope' => $scopeLabel,
            ], 'messages'),
            $url,
            NotificationSeverity::Info,
        );
    }
}

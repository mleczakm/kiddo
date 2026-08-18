<?php

declare(strict_types=1);

namespace App\Component;

use App\Application\Service\InAppNotificationService;
use App\Application\Service\LessonInstructorResolver;
use App\Application\Service\MoneyInputParser;
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
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

    private ?UploadedFile $uploadedImage = null;

    #[LiveProp(writable: true)]
    public bool $removeImage = false;

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

    // Plain 'Y-m-d' strings (not DateTimeImmutable): native <input type="date">
    // posts a date-only value, which doesn't match the datetime format
    // LiveComponent expects when hydrating a DateTimeImmutable-typed prop.
    #[LiveProp(writable: true)]
    public ?string $startDate = null;

    #[LiveProp(writable: true)]
    public ?string $endDate = null;

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

    public function mount(
        ?Ulid $seriesId = null,
        ?Ulid $lessonId = null,
        bool $startOpen = true,
        bool $endToday = false,
    ): void {
        if ($lessonId !== null) {
            $this->editingLessonId = $lessonId;
            $this->loadLessonData();
        } elseif ($seriesId !== null) {
            $this->editingSeriesId = $seriesId;
            $this->loadSeriesRepresentativeLesson();
        }
        if ($endToday) {
            $this->endDate = Clock::get()->now()->format('Y-m-d');
            $this->activeTab = 'schedule';
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
            if ($candidate->status !== 'active' || $candidate->schedule < $now) {
                continue;
            }
            if ($representative === null || $candidate->schedule < $representative->schedule) {
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
            $this->endDate = $series->lastOccurrenceDate?->format('Y-m-d');
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
        $this->occurrenceDate = $lesson->schedule->format('Y-m-d');
        $this->occurrenceTime = $lesson->schedule->format('H:i');

        $this->instructorIds = array_map(static fn(User $u) => (string) $u->getId(), $lesson->getAllInstructors());

        foreach ($lesson->getTicketOptions() as $option) {
            if ($option->type === TicketType::ONE_TIME) {
                $this->singleTicketPrice = (string) $option->price->getAmount();
            } elseif ($option->type === TicketType::CARNET_4) {
                $this->carnet4Price = (string) $option->price->getAmount();
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

    public function getEditingLessonImageUrl(): ?string
    {
        $lesson = $this->getEditingLesson();
        if ($lesson === null || !$lesson->getMetadata()->hasImage()) {
            return null;
        }

        return $this->urlGenerator->generate('workshop_image', [
            'id' => (string) $lesson->getId(),
        ]);
    }

    public function getEditingLessonIsVideo(): bool
    {
        return $this->getEditingLesson()?->getMetadata()->isVideo() ?? false;
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
            if ($sibling->status === 'active' && $sibling->schedule >= $now) {
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
            fn(User $user) => !in_array((string) $user->getId(), $this->instructorIds, true),
        ));
    }

    /**
     * @return list<string>
     */
    public function getCategories(): array
    {
        return ['Sensoryka', 'Muzyka', 'Ruchowe', 'Plastyczne', 'Brudna zabawa'];
    }

    /**
     * @return array<string, string>
     */
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
        if (!in_array($userId, $this->instructorIds, true)) {
            $this->instructorIds[] = $userId;
        }

        $this->instructorSearch = null;
    }

    #[LiveAction]
    public function removeInstructor(#[LiveArg] string $userId): void
    {
        $this->instructorIds = array_values(array_filter(
            $this->instructorIds,
            static fn(string $id) => $id !== $userId,
        ));
    }

    private const int MAX_IMAGE_BYTES = 3 * 1024 * 1024;

    // Larger than the image cap since video files are inherently heavier,
    // but still bounded to keep the DB row (stored as base64 text) sane.
    private const int MAX_VIDEO_BYTES = 20 * 1024 * 1024;

    /**
     * @var list<string>
     */
    private const array SUPPORTED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * MOV/QuickTime is deliberately excluded: browsers other than Safari
     * generally refuse to play a <video> whose src reports as
     * video/quicktime, even when the underlying codec is H.264 — same class
     * of trap as the HEIC photo issue. Ask for MP4/WebM instead of trying to
     * transcode client-side.
     *
     * @var list<string>
     */
    private const array SUPPORTED_VIDEO_MIME_TYPES = ['video/mp4', 'video/webm'];

    #[LiveAction]
    public function save(Request $request): void
    {
        $imageFile = $request->files->get('imageFile');
        $this->uploadedImage = $imageFile instanceof UploadedFile ? $imageFile : null;

        if ($this->title === null || $this->category === null || $this->description === null) {
            $this->addFlash('error', 'Wypełnij wszystkie wymagane pola.');
            return;
        }

        if ($this->uploadedImage !== null) {
            $mimeType = $this->uploadedImage->getMimeType();
            $isVideo = $mimeType !== null && str_starts_with($mimeType, 'video/');

            if ($mimeType === null || !str_starts_with($mimeType, 'image/') && !$isVideo) {
                $this->addFlash('error', 'Plik musi być zdjęciem lub filmem.');
                return;
            }

            $allowedTypes = $isVideo ? self::SUPPORTED_VIDEO_MIME_TYPES : self::SUPPORTED_IMAGE_MIME_TYPES;
            if (!in_array($mimeType, $allowedTypes, true)) {
                $this->addFlash(
                    'error',
                    $isVideo
                        ? 'Nieobsługiwany format wideo. Użyj MP4 lub WebM (nie MOV).'
                        : 'Nieobsługiwany format zdjęcia. Użyj JPG, PNG, WebP lub GIF (nie HEIC).',
                );
                return;
            }

            $maxBytes = $isVideo ? self::MAX_VIDEO_BYTES : self::MAX_IMAGE_BYTES;
            $uploadedSize = $this->uploadedImage->getSize();
            if ($uploadedSize !== false && $uploadedSize > $maxBytes) {
                $this->addFlash(
                    'error',
                    $isVideo ? 'Plik wideo jest za duży (maks. 20 MB).' : 'Zdjęcie jest za duże (maks. 3 MB).',
                );
                return;
            }
        }

        try {
            $ticketOptions = $this->buildTicketOptions();
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'Podaj poprawną cenę biletu, np. 55,00.');
            return;
        }

        if ($this->editingLessonId !== null) {
            $this->saveExistingLesson($ticketOptions);
        } else {
            $this->saveNewSeries($ticketOptions);
        }
    }

    /**
     * @param list<TicketOption> $ticketOptions
     */
    private function saveExistingLesson(array $ticketOptions): void
    {
        $lesson = $this->entityManager->find(Lesson::class, $this->editingLessonId);
        if ($lesson === null) {
            $this->addFlash('error', 'Nie znaleziono zajęć.');
            return;
        }

        if (!$this->isGranted(LessonVoter::MANAGE, $lesson)) {
            $this->addFlash('error', 'Nie masz uprawnień do edycji tych zajęć.');
            return;
        }

        $series = $lesson->getSeries();
        $scope = $this->editScope === 'series' && $series !== null ? 'series' : 'occurrence';

        $scheduleString = trim(($this->occurrenceDate ?? '') . ' ' . ($this->occurrenceTime ?? ''));
        try {
            $newSchedule = new \DateTimeImmutable($scheduleString);
        } catch (\Exception) {
            $this->addFlash('error', 'Nieprawidłowa data lub godzina.');
            return;
        }

        $affectedLessons = [$lesson];
        $lesson->schedule = $newSchedule;

        // Last-occurrence date is a series-wide scheduling setting (stops
        // ExtendSeriesScheduleHandler from generating further occurrences),
        // independent of the content edit scope — only ROLE_MANAGE_SCHEDULE
        // can change it, same as creating/cancelling a series.
        if ($series !== null && $this->isGranted('ROLE_MANAGE_SCHEDULE')) {
            $series->lastOccurrenceDate = $this->parseDate($this->endDate);
        }

        if ($scope === 'series') {
            if ($series === null) {
                throw new \LogicException('A series edit requires the lesson to belong to a series.');
            }

            // One shared metadata row for the whole series, rather than each
            // occurrence duplicating its own copy — every affected lesson
            // (this one and its active upcoming siblings) points at the same
            // LessonMetadata; only each Lesson's own schedule stays distinct.
            $sharedMetadata = $this->buildMetadataFor($lesson);
            $lesson->setMetadata($sharedMetadata);

            $now = Clock::get()->now();
            foreach ($series->lessons as $sibling) {
                if ($sibling === $lesson || $sibling->status !== 'active' || $sibling->schedule < $now) {
                    continue;
                }
                // Content (and the shared metadata row) propagates; each
                // occurrence keeps its own date/time.
                $sibling->setMetadata($sharedMetadata);
                $affectedLessons[] = $sibling;
            }
        } else {
            $lesson->setMetadata($this->buildMetadataFor($lesson));
        }

        $instructors = $this->resolveSelectedInstructors();

        if ($scope === 'series') {
            \assert($series !== null, 'A series edit requires the lesson to belong to a series.');
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

    /**
     * @param list<TicketOption> $ticketOptions
     */
    private function saveNewSeries(array $ticketOptions): void
    {
        if (!$this->isGranted('ROLE_MANAGE_SCHEDULE')) {
            $this->addFlash('error', 'Nie masz uprawnień do tworzenia nowych warsztatów.');
            return;
        }

        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $series->lastOccurrenceDate = $this->parseDate($this->endDate);
        $this->entityManager->persist($series);

        $instructors = $this->resolveSelectedInstructors();
        foreach ($instructors as $user) {
            $series->addInstructor($user);
        }

        $series->ticketOptions = $ticketOptions;

        $metadata = new LessonMetadata(
            title: (string) $this->title,
            lead: $this->lead ?? '',
            visualTheme: $this->visualTheme ?? LessonMetadata::DEFAULT_VISUAL_THEME,
            description: (string) $this->description,
            capacity: $this->capacity ?? 10,
            duration: $this->duration ?? 90,
            ageRange: new AgeRange($this->ageMin ?? 0, $this->ageMax ?? 10),
            category: (string) $this->category,
        );

        $upload = $this->readUploadedImage();
        if ($upload !== null) {
            $metadata = $metadata->withImage($upload['data'], $upload['mime']);
        }

        $lesson = new Lesson($metadata, $this->parseDate($this->startDate) ?? Clock::get()->now());
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

    private function buildMetadataFor(Lesson $lesson): LessonMetadata
    {
        $metadata = $lesson
            ->getMetadata()
            ->withTitle((string) $this->title)
            ->withLead($this->lead ?? '')
            ->withVisualTheme($this->visualTheme ?? LessonMetadata::DEFAULT_VISUAL_THEME)
            ->withDescription((string) $this->description)
            ->withCapacity($this->capacity ?? 10)
            ->withDuration($this->duration ?? 90)
            ->withAgeRange(new AgeRange($this->ageMin ?? 0, $this->ageMax ?? 10))
            ->withCategory((string) $this->category);

        if ($this->removeImage) {
            $metadata = $metadata->withImage(null, null);
        } else {
            $upload = $this->readUploadedImage();
            if ($upload !== null) {
                $metadata = $metadata->withImage($upload['data'], $upload['mime']);
            }
        }

        return $metadata;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array{data: string, mime: string}|null
     */
    private function readUploadedImage(): ?array
    {
        if ($this->uploadedImage === null) {
            return null;
        }

        $contents = file_get_contents($this->uploadedImage->getPathname());
        if ($contents === false) {
            return null;
        }

        return [
            'data' => base64_encode($contents),
            'mime' => $this->uploadedImage->getMimeType() ?? 'application/octet-stream',
        ];
    }

    /**
     * @return list<TicketOption>
     */
    private function buildTicketOptions(): array
    {
        $ticketOptions = [];

        $singlePrice = MoneyInputParser::parse($this->singleTicketPrice);
        if ($singlePrice !== null) {
            $ticketOptions[] = new TicketOption(
                TicketType::ONE_TIME,
                Money::of($singlePrice, 'PLN'),
                'Wejście jednorazowe',
                TicketReschedulePolicy::UNLIMITED_24H_BEFORE,
            );
        }

        $carnet4Price = MoneyInputParser::parse($this->carnet4Price);
        if ($carnet4Price !== null) {
            $ticketOptions[] = new TicketOption(
                TicketType::CARNET_4,
                Money::of($carnet4Price, 'PLN'),
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
        if (!$editor instanceof User || $affectedLessons === []) {
            return;
        }

        $lessonTitle = $affectedLessons[0]->getMetadata()->title;
        $scopeLabel = $this->translator->trans(
            $scope === 'series'
                ? 'admin.workshop_editor.scope.series_short'
                : 'admin.workshop_editor.scope.occurrence_short',
            [],
            'messages',
        );
        $url = $this->urlGenerator->generate('app_admin_lesson_view', [
            'id' => (string) $affectedLessons[0]->getId(),
        ]);

        $instructors = $this->instructorResolver->resolve($affectedLessons, exclude: $editor);
        $this->inAppNotifications->notifyUsers(
            $instructors,
            $this->translator->trans('notifications.in_app.workshop_edited.instructor.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.workshop_edited.instructor.body',
                [
                    'editor' => $editor->getName(),
                    'lesson' => $lessonTitle,
                    'scope' => $scopeLabel,
                ],
                'messages',
            ),
            $url,
            NotificationSeverity::Info,
        );

        $instructorIds = array_map(static fn(User $u) => $u->getId(), $instructors);
        $admins = array_values(array_filter(
            $this->userRepository->findByRole('ROLE_ADMIN'),
            static fn(User $admin) => $admin->getId() !== $editor->getId()
            && !in_array($admin->getId(), $instructorIds, true),
        ));
        $this->inAppNotifications->notifyUsers(
            $admins,
            $this->translator->trans('notifications.in_app.workshop_edited.admin.title', [], 'messages'),
            $this->translator->trans(
                'notifications.in_app.workshop_edited.admin.body',
                [
                    'editor' => $editor->getName(),
                    'lesson' => $lessonTitle,
                    'scope' => $scopeLabel,
                ],
                'messages',
            ),
            $url,
            NotificationSeverity::Info,
        );
    }
}

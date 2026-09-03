<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\File\WorkshopImageUploadPolicy;
use App\Application\Repository\FileRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Application\Service\LessonInstructorResolver;
use App\Application\Service\MoneyInputParser;
use App\Application\Service\SeriesScheduleImpact;
use App\Application\Service\SeriesScheduleSynchronizer;
use App\Application\Service\WorkshopFileManager;
use App\Entity\AgeRange;
use App\Entity\File;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\NotificationSeverity;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\User;
use App\Entity\WorkshopFileRole;
use App\Entity\WorkshopType;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Infrastructure\Symfony\Security\Voter\LessonVoter;
use Brick\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
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
 *  - 'following': this Lesson and every later active occurrence.
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
     * 'occurrence', 'following' or 'series' — only meaningful when editingLessonId is set
     * and that lesson belongs to a Series.
     */
    #[LiveProp(writable: true)]
    public string $editScope = 'occurrence';

    #[LiveProp(writable: true)]
    public bool $visible = true;

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
    public string $newAttachmentRole = 'attachment';

    /** @var array<string, string> */
    #[LiveProp(writable: true)]
    public array $attachmentRoles = [];

    /** @var array<string, string> */
    #[LiveProp(writable: true)]
    public array $attachmentCaptions = [];

    /** @var list<string> */
    #[LiveProp(writable: true)]
    public array $removedAttachmentIds = [];

    #[LiveProp(writable: true)]
    public ?string $mediaLibrarySearch = null;

    /**
     * File ids picked from the media library, staged to be attached (with
     * $newAttachmentRole) on save — same deferred-until-save pattern as a
     * freshly uploaded file, since a brand-new lesson has no persisted
     * LessonMetadata to attach to yet.
     *
     * @var list<string>
     */
    #[LiveProp(writable: true)]
    public array $libraryAttachmentIds = [];

    private ?UploadedFile $uploadedImage = null;

    /** @var list<UploadedFile> */
    private array $uploadedAttachments = [];

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

    /** Monthly-subscription price (whole span of a month's series lessons). Gated by the `subscriptions` flag. */
    #[LiveProp(writable: true)]
    public ?string $monthlyPrice = null;

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
        private readonly WorkshopFileManager $workshopFileManager,
        private readonly SeriesScheduleSynchronizer $scheduleSynchronizer,
        private readonly FileRepositoryInterface $fileRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        #[Autowire(service: 'html_sanitizer.sanitizer.app.article_sanitizer')]
        private readonly HtmlSanitizerInterface $descriptionSanitizer,
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

        $this->visible = $this->editScope === 'series' && $series !== null ? $series->visible : $lesson->visible;

        $this->loadMetadataFields($lesson->getMetadata());
        $this->occurrenceDate = $lesson->schedule->format('Y-m-d');
        $this->occurrenceTime = $lesson->schedule->format('H:i');
        $this->instructorIds = array_map(static fn(User $u) => (string) $u->getId(), $lesson->getAllInstructors());
        $this->loadTicketPrices($lesson);
    }

    private function loadMetadataFields(LessonMetadata $metadata): void
    {
        $this->title = $metadata->title;
        $this->category = $metadata->category;
        $this->description = $metadata->description;
        $this->lead = $metadata->lead;
        $this->visualTheme = $metadata->visualTheme;
        $this->ageMin = $metadata->ageRange->min;
        $this->ageMax = $metadata->ageRange->max;
        $this->capacity = $metadata->capacity;
        $this->duration = $metadata->duration;
        foreach ($metadata->files as $workshopFile) {
            $fileId = (string) $workshopFile->getFile()->getId();
            $this->attachmentRoles[$fileId] = $workshopFile->getRole()->value;
            $this->attachmentCaptions[$fileId] = $workshopFile->getCaption() ?? '';
        }
    }

    private function loadTicketPrices(Lesson $lesson): void
    {
        foreach ($lesson->getTicketOptions() as $option) {
            if ($option->type === TicketType::ONE_TIME) {
                $this->singleTicketPrice = (string) $option->price->getAmount();
                continue;
            }
            if ($option->type === TicketType::CARNET_4) {
                $this->carnet4Price = (string) $option->price->getAmount();
                continue;
            }
            if ($option->type === TicketType::MONTHLY) {
                $this->monthlyPrice = (string) $option->price->getAmount();
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

    public function getFollowingSeriesLessonsCount(): int
    {
        $lesson = $this->getEditingLesson();
        if ($lesson === null || $lesson->getSeries() === null) {
            return 0;
        }

        return count($this->getAffectedLessons($lesson, 'following')) - 1;
    }

    /**
     * Exact operation counts shown next to the save button before the admin commits the change.
     *
     * @return array{create: int, hide: int, delete: int, update: int}
     */
    public function getSaveImpact(): array
    {
        $impact = new SeriesScheduleImpact();
        $update = 0;

        try {
            if ($this->editingLessonId === null) {
                $start = $this->parseScheduleDateTime($this->startDate, $this->startTime);
                $type = $this->scheduleType === 'single' ? WorkshopType::ONE_TIME : WorkshopType::WEEKLY;
                $end = $type === WorkshopType::ONE_TIME ? $start : $this->requireEndDate();
                $impact = $this->scheduleSynchronizer->previewNew($type, $start, $end);
            } else {
                $lesson = $this->getEditingLesson();
                if ($lesson !== null) {
                    $scope = $this->normalizeScope($lesson);
                    $update = count($this->getAffectedLessons($lesson, $scope));
                    $series = $lesson->getSeries();
                    if ($series !== null && $this->isGranted('ROLE_MANAGE_SCHEDULE')) {
                        $end = $this->parseExistingSeriesEndDate($series);
                        if ($this->seriesEndChanged($series, $end)) {
                            $impact = $this->scheduleSynchronizer->previewExisting($series, $end);
                        }
                    }
                }
            }
        } catch (\DomainException|\InvalidArgumentException) {
            // Incomplete form values are validated on save; the preview simply stays at zero meanwhile.
            $impact = new SeriesScheduleImpact();
            $update = 0;
        }

        return [
            'create' => $impact->create,
            'hide' => $impact->hide,
            'delete' => $impact->delete,
            'update' => $update,
        ];
    }

    /**
     * @return list<WorkshopFileRole>
     */
    public function getAttachmentRoleOptions(): array
    {
        return WorkshopFileRole::cases();
    }

    /**
     * @return list<array{fileId: string, name: string, size: int, mimeType: string, role: string, caption: string, isRemoved: bool}>
     */
    public function getAttachments(): array
    {
        $lesson = $this->getEditingLesson();
        if ($lesson === null) {
            return [];
        }

        $attachments = [];
        foreach ($lesson->getMetadata()->files as $workshopFile) {
            $file = $workshopFile->getFile();
            $fileId = (string) $file->getId();
            $attachments[] = [
                'fileId' => $fileId,
                'name' => $file->getOriginalName(),
                'size' => $file->getSize(),
                'mimeType' => $file->getMimeType(),
                'role' => $this->attachmentRoles[$fileId] ?? $workshopFile->getRole()->value,
                'caption' => $this->attachmentCaptions[$fileId] ?? $workshopFile->getCaption() ?? '',
                'isRemoved' => \in_array($fileId, $this->removedAttachmentIds, true),
            ];
        }

        return $attachments;
    }

    /**
     * Media-library search results: any previously uploaded file (from any
     * workshop, or a post), so an admin can reuse it instead of re-uploading
     * the same document (e.g. a shared terms-of-use PDF) to every workshop.
     * Excludes files already attached or already staged for attachment.
     *
     * @return list<array{id: string, name: string, size: int, mimeType: string}>
     */
    public function getMediaLibraryResults(): array
    {
        $search = $this->mediaLibrarySearch;
        if ($search === null || mb_strlen(trim($search)) < 2) {
            return [];
        }

        $excludedIds = $this->libraryAttachmentIds;
        $lesson = $this->getEditingLesson();
        if ($lesson !== null) {
            foreach ($lesson->getMetadata()->files as $workshopFile) {
                $excludedIds[] = (string) $workshopFile->getFile()->getId();
            }
        }

        $matches = array_filter(
            $this->fileRepository->search($search),
            static fn(File $file) => !\in_array((string) $file->getId(), $excludedIds, true),
        );

        return array_values(array_map($this->describeFile(...), $matches));
    }

    /**
     * @return list<array{id: string, name: string, size: int, mimeType: string}>
     */
    public function getStagedLibraryFiles(): array
    {
        $files = array_filter(array_map($this->findFile(...), $this->libraryAttachmentIds));

        return array_values(array_map($this->describeFile(...), $files));
    }

    /**
     * @return array{id: string, name: string, size: int, mimeType: string}
     */
    private function describeFile(File $file): array
    {
        return [
            'id' => (string) $file->getId(),
            'name' => $file->getOriginalName(),
            'size' => $file->getSize(),
            'mimeType' => $file->getMimeType(),
        ];
    }

    private function findFile(string $fileId): ?File
    {
        try {
            $id = Ulid::fromString($fileId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->fileRepository->find($id);
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
        return ['Sensoryka', 'Muzyka', 'Ruchowe', 'Plastyczne', 'Brudna zabawa', 'Rozwój ogólny'];
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

    #[LiveAction]
    public function toggleRemoveAttachment(#[LiveArg] string $fileId): void
    {
        if (\in_array($fileId, $this->removedAttachmentIds, true)) {
            $this->removedAttachmentIds = array_values(array_filter(
                $this->removedAttachmentIds,
                static fn(string $id) => $id !== $fileId,
            ));
        } else {
            $this->removedAttachmentIds[] = $fileId;
        }
    }

    #[LiveAction]
    public function addLibraryFile(#[LiveArg] string $fileId): void
    {
        if (\in_array($fileId, $this->libraryAttachmentIds, true) || $this->findFile($fileId) === null) {
            return;
        }

        $this->libraryAttachmentIds[] = $fileId;
        $this->mediaLibrarySearch = null;
    }

    #[LiveAction]
    public function removeLibraryFile(#[LiveArg] string $fileId): void
    {
        $this->libraryAttachmentIds = array_values(array_filter(
            $this->libraryAttachmentIds,
            static fn(string $id) => $id !== $fileId,
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
        $attachmentFiles = $request->files->all('attachmentFiles');
        $this->uploadedAttachments = array_values(array_filter(
            $attachmentFiles,
            static fn(mixed $file): bool => $file instanceof UploadedFile,
        ));

        $this->description = $this->descriptionSanitizer->sanitize($this->description ?? '');

        if ($this->title === null || !$this->category || trim(strip_tags($this->description)) === '') {
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

            $allowedImageMimes = WorkshopImageUploadPolicy::supportedImageMimes();
            $allowedVideoMimes = WorkshopImageUploadPolicy::supportedVideoMimes();
            $allowedTypes = $isVideo ? $allowedVideoMimes : $allowedImageMimes;
            if (!in_array($mimeType, $allowedTypes, true)) {
                $this->addFlash(
                    'error',
                    $isVideo
                        ? 'Nieobsługiwany format wideo. Użyj MP4 lub WebM (nie MOV).'
                        : 'Nieobsługiwany format zdjęcia. Użyj JPG, PNG, WebP lub GIF (nie HEIC).',
                );
                return;
            }

            $uploadedSize = $this->uploadedImage->getSize();
            if ($uploadedSize === false) {
                $this->addFlash('error', 'Nie można odczytać rozmiaru pliku.');
                return;
            }

            try {
                $policy = new WorkshopImageUploadPolicy();
                $policy->assertValidUpload($mimeType, $uploadedSize);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
                return;
            }
        }

        try {
            $ticketOptions = $this->buildTicketOptions();
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'Podaj poprawną cenę biletu, np. 55,00.');
            return;
        }

        try {
            if ($this->editingLessonId !== null) {
                $this->saveExistingLesson($ticketOptions);
            } else {
                $this->saveNewSeries($ticketOptions);
            }
        } catch (\DomainException|\InvalidArgumentException|\ValueError $e) {
            $this->addFlash('error', $e->getMessage());
        }
    }

    /**
     * @param list<TicketOption> $ticketOptions
     *
     * @throws \DomainException
     * @throws \InvalidArgumentException
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
        $scope = $this->normalizeScope($lesson);

        $scheduleString = trim(($this->occurrenceDate ?? '') . ' ' . ($this->occurrenceTime ?? ''));
        try {
            $newSchedule = new \DateTimeImmutable($scheduleString);
        } catch (\Exception) {
            $this->addFlash('error', 'Nieprawidłowa data lub godzina.');
            return;
        }

        $lesson->schedule = $newSchedule;

        // The end date is series-wide and immediately reconciles materialized
        // occurrences, independent of the content edit scope. Only schedule
        // managers can change it, same as creating/cancelling a series.
        if ($series !== null && $this->isGranted('ROLE_MANAGE_SCHEDULE')) {
            $end = $this->parseExistingSeriesEndDate($series);
            if ($this->seriesEndChanged($series, $end)) {
                $this->scheduleSynchronizer->synchronize($series, $end);
            }
        }

        if ($series !== null && !$series->lessons->contains($lesson)) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Harmonogram warsztatu został zaktualizowany.');
            $this->isModalOpen = false;
            $this->emitUp('workshopEditorSaved');
            return;
        }

        // Recompute after schedule synchronization so newly-created lessons
        // are included in a "this and following" or whole-series edit.
        $affectedLessons = $this->getAffectedLessons($lesson, $scope);

        if ($scope !== 'occurrence') {
            if ($series === null) {
                throw new \LogicException('A series edit requires the lesson to belong to a series.');
            }

            // One shared metadata row for the whole series, rather than each
            // occurrence duplicating its own copy — every affected lesson
            // (this one and its active upcoming siblings) points at the same
            // LessonMetadata; only each Lesson's own schedule stays distinct.
            $sharedMetadata = $this->buildMetadataFor($lesson);
            $lesson->setMetadata($sharedMetadata);

            foreach ($affectedLessons as $sibling) {
                // Content (and the shared metadata row) propagates; each
                // occurrence keeps its own date/time.
                $sibling->setMetadata($sharedMetadata);
            }
        } else {
            $lesson->setMetadata($this->buildMetadataFor($lesson));
        }

        $this->reconcileWorkshopFiles($lesson->getMetadata());

        $this->applyEditScope($lesson, $scope, $affectedLessons, $ticketOptions);

        $this->entityManager->flush();

        $this->notifyAboutEdit($affectedLessons, $scope);

        $this->addFlash('success', 'Warsztat został zaktualizowany.');
        $this->isModalOpen = false;
        $this->emitUp('workshopEditorSaved');
    }

    /**
     * @param list<Lesson> $affectedLessons
     * @param list<TicketOption> $ticketOptions
     */
    private function applyEditScope(Lesson $lesson, string $scope, array $affectedLessons, array $ticketOptions): void
    {
        $series = $lesson->getSeries();
        $instructors = $this->resolveSelectedInstructors();

        if ($scope === 'series') {
            \assert($series !== null, 'A series edit requires the lesson to belong to a series.');
            $series->ticketOptions = $ticketOptions;
            $series->visible = $this->visible;
            $lesson->setTicketOptions();
            $series->instructors->clear();
            foreach ($instructors as $user) {
                $series->addInstructor($user);
            }
            $lesson->getInstructors()->clear();

            return;
        }

        if ($scope === 'following') {
            \assert($series !== null, 'A following edit requires the lesson to belong to a series.');
            foreach ($affectedLessons as $affectedLesson) {
                $affectedLesson->setTicketOptions(...$ticketOptions);
                $affectedLesson->visible = $this->visible;
                $affectedLesson->getInstructors()->clear();
                foreach ($instructors as $user) {
                    $affectedLesson->addInstructor($user);
                }
            }

            return;
        }

        $lesson->setTicketOptions(...$ticketOptions);
        $lesson->visible = $this->visible;
        $lesson->getInstructors()->clear();
        foreach ($instructors as $user) {
            $lesson->addInstructor($user);
        }
    }

    /**
     * @param list<TicketOption> $ticketOptions
     *
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    private function saveNewSeries(array $ticketOptions): void
    {
        if (!$this->isGranted('ROLE_MANAGE_SCHEDULE')) {
            $this->addFlash('error', 'Nie masz uprawnień do tworzenia nowych warsztatów.');
            return;
        }

        $type = $this->scheduleType === 'single' ? WorkshopType::ONE_TIME : WorkshopType::WEEKLY;
        $start = $this->parseScheduleDateTime($this->startDate, $this->startTime);
        $end = $type === WorkshopType::ONE_TIME ? $start : $this->requireEndDate();
        $series = new Series(new ArrayCollection(), $type, visible: $this->visible);
        $series->lastOccurrenceDate = $end;
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
            duration: $this->duration ?? $this->computeDurationMinutes($this->startTime, $this->endTime),
            ageRange: new AgeRange($this->ageMin ?? 0, $this->ageMax ?? 10),
            category: (string) $this->category,
        );

        $upload = $this->readUploadedImage();
        if ($upload !== null) {
            $metadata = $metadata->withImage($upload['data'], $upload['mime']);
        }

        $lessons = $this->scheduleSynchronizer->createInitialLessons($series, $metadata, $start, $end);
        if ($lessons === []) {
            throw new \LogicException('Nie utworzono żadnego terminu zajęć.');
        }
        $this->reconcileWorkshopFiles($metadata);
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
            return $metadata->withImage(null, null);
        }

        $upload = $this->readUploadedImage();
        if ($upload !== null) {
            return $metadata->withImage($upload['data'], $upload['mime']);
        }

        return $metadata;
    }

    /**
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    private function reconcileWorkshopFiles(LessonMetadata $metadata): void
    {
        $this->workshopFileManager->reconcile(
            $metadata,
            $this->attachmentRoles,
            $this->attachmentCaptions,
            $this->removedAttachmentIds,
        );

        $role = WorkshopFileRole::from($this->newAttachmentRole);

        if ($this->uploadedAttachments !== []) {
            $user = $this->getUser();
            $this->workshopFileManager->attachUploads(
                $metadata,
                $this->uploadedAttachments,
                $role,
                $user instanceof User ? $user : null,
            );
        }

        foreach ($this->libraryAttachmentIds as $fileId) {
            $file = $this->findFile($fileId);
            if ($file === null) {
                continue;
            }

            $this->workshopFileManager->attachExisting($metadata, $file, $role, $metadata->files->count());
        }
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

    /** @throws \InvalidArgumentException */
    private function requireEndDate(): \DateTimeImmutable
    {
        $end = $this->parseDate($this->endDate);
        if ($end === null) {
            throw new \InvalidArgumentException(
                'Podaj datę zakończenia cyklu, aby utworzyć wszystkie terminy od razu.',
            );
        }

        return $end;
    }

    private function seriesEndChanged(Series $series, ?\DateTimeImmutable $end): bool
    {
        return $series->lastOccurrenceDate?->format('Y-m-d') !== $end?->format('Y-m-d');
    }

    /** @throws \InvalidArgumentException */
    private function parseExistingSeriesEndDate(Series $series): ?\DateTimeImmutable
    {
        if ($series->type !== WorkshopType::WEEKLY) {
            return $this->parseDate($this->endDate);
        }

        // Legacy open-ended series may still be edited without inventing an
        // arbitrary cutoff. Once an end date is supplied, it is required and
        // all dates through it are materialized immediately.
        if (($this->endDate === null || $this->endDate === '') && $series->lastOccurrenceDate === null) {
            return null;
        }

        return $this->requireEndDate();
    }

    private function normalizeScope(Lesson $lesson): string
    {
        if ($lesson->getSeries() === null || !in_array($this->editScope, ['following', 'series'], true)) {
            return 'occurrence';
        }

        return $this->editScope;
    }

    /**
     * @return list<Lesson>
     */
    private function getAffectedLessons(Lesson $lesson, string $scope): array
    {
        $series = $lesson->getSeries();
        if ($series === null || $scope === 'occurrence') {
            return [$lesson];
        }

        $threshold = $scope === 'following' ? $lesson->schedule : Clock::get()->now();
        $affected = [];
        foreach ($series->lessons as $candidate) {
            if (!($candidate === $lesson || $candidate->status === 'active' && $candidate->schedule >= $threshold)) {
                continue;
            }

            $affected[] = $candidate;
        }

        usort($affected, static fn(Lesson $a, Lesson $b): int => $a->schedule <=> $b->schedule);

        return $affected;
    }

    /**
     * Combines the "Początek cyklu" date with the "Godzina Od" time picked
     * on the schedule tab, so a new series' first occurrence starts at the
     * chosen hour instead of defaulting to midnight.
     *
     * @throws \InvalidArgumentException
     */
    private function parseScheduleDateTime(?string $date, ?string $time): \DateTimeImmutable
    {
        if ($date === null || $date === '' || $time === null || $time === '') {
            throw new \InvalidArgumentException('Podaj datę i godzinę rozpoczęcia warsztatu.');
        }

        try {
            return new \DateTimeImmutable($date . ' ' . $time);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Nieprawidłowa data lub godzina rozpoczęcia.');
        }
    }

    /**
     * Derives a workshop's length in minutes from the "Godzina Od - Do"
     * range picked on the schedule tab, since there is no separate raw
     * duration input.
     *
     * @throws \InvalidArgumentException
     */
    private function computeDurationMinutes(?string $startTime, ?string $endTime): int
    {
        if ($startTime === null || $startTime === '' || $endTime === null || $endTime === '') {
            throw new \InvalidArgumentException('Podaj godzinę rozpoczęcia i zakończenia warsztatu.');
        }

        try {
            $start = new \DateTimeImmutable($startTime);
            $end = new \DateTimeImmutable($endTime);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Nieprawidłowy format godziny.');
        }

        $minutes = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);

        if ($minutes <= 0) {
            throw new \InvalidArgumentException('Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia.');
        }

        return $minutes;
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

        $monthlyPrice = MoneyInputParser::parse($this->monthlyPrice);
        if ($monthlyPrice !== null) {
            $ticketOptions[] = new TicketOption(
                TicketType::MONTHLY,
                Money::of($monthlyPrice, 'PLN'),
                'Abonament miesięczny',
                TicketReschedulePolicy::NOT_ALLOWED,
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
            match ($scope) {
                'series' => 'admin.workshop_editor.scope.series_short',
                'following' => 'admin.workshop_editor.scope.following_short',
                default => 'admin.workshop_editor.scope.occurrence_short',
            },
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

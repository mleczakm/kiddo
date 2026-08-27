<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\File;
use App\Entity\Lesson;
use App\Entity\Series;
use App\Entity\User;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Entity\WorkshopType;
use App\Infrastructure\Doctrine\Repository\NotificationRepository;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\WorkshopEditorComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class WorkshopEditorComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private NotificationRepository $notificationRepository;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->notificationRepository = static::getContainer()->get(NotificationRepository::class);
    }

    public function testMountPrefillsFieldsFromExistingLesson(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()
            ->withTitle('Błotna Kuchnia')
            ->withSchedule(new \DateTimeImmutable('2030-06-15 10:30'))
            ->assemble();
        $this->em->persist($lesson);
        $this->em->flush();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lesson->getId(),
            'startOpen' => false,
        ]);

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        static::assertSame('Błotna Kuchnia', $workshopEditorComponent->title);
        static::assertSame('2030-06-15', $workshopEditorComponent->occurrenceDate);
        static::assertSame('10:30', $workshopEditorComponent->occurrenceTime);
    }

    public function testMountPrefillsTicketPriceInZlotyNotGrosze(): void
    {
        // Regression test: the ticket price fields used to be hydrated from
        // Money::getMinorAmount() (grosze, e.g. 5000) while the input's
        // placeholder implied whole zloty (e.g. "55") — an admin editing an
        // existing lesson would see "5000" in a field that looked like it
        // expected "50".
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble(); // default ticket option: 50 PLN
        $this->em->persist($lesson);
        $this->em->flush();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lesson->getId(),
            'startOpen' => false,
        ]);

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        static::assertSame('50.00', $workshopEditorComponent->singleTicketPrice);
    }

    public function testSavingTicketPriceAcceptsCommaDecimalAndPersistsCorrectAmount(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('singleTicketPrice', '75,50');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        $ticketOption = $reloaded->getTicketOptions()[0] ?? null;
        static::assertNotNull($ticketOption);
        static::assertTrue($ticketOption->price->isEqualTo(Money::of('75.50', 'PLN')));
    }

    public function testEditingAnExistingLessonCanChangeItsDuration(): void
    {
        // Regression test: the editor had no field at all to change an
        // existing workshop's duration after creation.
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->withDuration(60)->assemble();
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $workshopEditorComponent = $component->component();
        static::assertInstanceOf(WorkshopEditorComponent::class, $workshopEditorComponent);
        static::assertSame(60, $workshopEditorComponent->duration);

        $component->set('duration', 120);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertSame(120, $reloaded->getMetadata()->duration);
    }

    public function testCreatingNewSeriesDerivesDurationAndStartTimeFromTheHourRange(): void
    {
        // Regression test: the "Godzina (Od - Do)" schedule-tab picker used
        // to be captured into startTime/endTime and then silently ignored —
        // every new workshop was hardcoded to 90 minutes starting at
        // midnight, regardless of what was picked here.
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'startOpen' => false,
        ]);

        $component->set('title', 'Nowy Warsztat');
        $component->set('category', 'sztuka');
        $component->set('description', 'Opis warsztatu');
        $component->set('scheduleType', 'recurring');
        $component->set('startDate', '2030-07-08');
        $component->set('endDate', '2030-07-22');
        $component->set('startTime', '10:00');
        $component->set('endTime', '11:15');
        $component->call('save');

        $this->em->clear();
        /** @var list<Lesson> $lessons */
        $lessons = $this->em
            ->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.metadata', 'm')
            ->where('m.title = :title')
            ->setParameter('title', 'Nowy Warsztat')
            ->orderBy('l.schedule', 'ASC')
            ->getQuery()
            ->getResult();

        static::assertCount(3, $lessons);
        $lesson = $lessons[0];
        static::assertTrue($lesson->visible);
        $series = $lesson->getSeries();
        static::assertNotNull($series);
        static::assertTrue($series->visible);
        static::assertSame(75, $lesson->getMetadata()->duration);
        static::assertSame('2030-07-08 10:00', $lesson->schedule->format('Y-m-d H:i'));
        static::assertCount(3, $lesson->getSeries()->lessons ?? []);
    }

    public function testCreatingNewSeriesWithAnEndTimeBeforeStartTimeIsRejectedWithoutSaving(): void
    {
        // There is no sensible fallback duration for a nonsensical hour
        // range — this must be rejected rather than silently saved as some
        // made-up length.
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'startOpen' => false,
        ]);

        $component->set('title', 'Warsztat Z Błędną Godziną');
        $component->set('category', 'sztuka');
        $component->set('description', 'Opis warsztatu');
        $component->set('scheduleType', 'recurring');
        $component->set('startDate', '2030-07-08');
        $component->set('endDate', '2030-07-22');
        $component->set('startTime', '11:00');
        $component->set('endTime', '10:00');
        $component->call('save');

        $this->em->clear();
        /** @var ?Lesson $lesson */
        $lesson = $this->em
            ->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.metadata', 'm')
            ->where('m.title = :title')
            ->setParameter('title', 'Warsztat Z Błędną Godziną')
            ->getQuery()
            ->getOneOrNullResult();

        static::assertNull($lesson);
    }

    public function testInvalidTicketPriceIsRejectedWithoutSaving(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble(); // default ticket option: 50 PLN
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('singleTicketPrice', 'not-a-number');
        $component->set('title', 'Should Not Persist');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertNotSame('Should Not Persist', $reloaded->getMetadata()->title);
        $ticketOption = $reloaded->getTicketOptions()[0] ?? null;
        static::assertNotNull($ticketOption);
        static::assertTrue($ticketOption->price->isEqualTo(Money::of('50.00', 'PLN')));
    }

    public function testImageUploadIsPersistedWhenSavingExistingLesson(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $imagePath = tempnam(sys_get_temp_dir(), 'workshop-image-');
        static::assertNotFalse($imagePath);
        $imageData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        static::assertNotFalse($imageData);
        file_put_contents($imagePath, $imageData);

        try {
            $this->client->loginUser($admin);
            $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
                'lessonId' => $lessonId,
                'startOpen' => false,
            ]);

            $component->call('save', files: [
                'imageFile' => new UploadedFile($imagePath, 'workshop.png', 'image/png', null, true),
            ]);

            $this->em->clear();
            $reloaded = $this->em->find(Lesson::class, $lessonId);
            static::assertNotNull($reloaded);
            static::assertSame('image/png', $reloaded->getMetadata()->imageMimeType);
            static::assertSame(base64_encode($imageData), $reloaded->getMetadata()->imageData);
        } finally {
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }
    }

    public function testAttachmentUploadIsPersistedWithSelectedRole(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $attachmentPath = tempnam(sys_get_temp_dir(), 'workshop-attachment-');
        static::assertNotFalse($attachmentPath);
        file_put_contents($attachmentPath, '%PDF-1.4 test content');

        try {
            $this->client->loginUser($admin);
            $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
                'lessonId' => $lessonId,
                'startOpen' => false,
            ]);

            $component->set('newAttachmentRole', 'terms_of_use');
            $component->call('save', files: [
                'attachmentFiles' => [
                    new UploadedFile($attachmentPath, 'materialy.pdf', 'application/pdf', null, true),
                ],
            ]);

            $this->em->clear();
            $reloaded = $this->em->find(Lesson::class, $lessonId);
            static::assertNotNull($reloaded);
            static::assertCount(1, $reloaded->getMetadata()->files);
            $workshopFile = $reloaded->getMetadata()->files->first();
            static::assertInstanceOf(WorkshopFile::class, $workshopFile);
            static::assertSame('materialy.pdf', $workshopFile->getFile()->getOriginalName());
            static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
        } finally {
            if (is_file($attachmentPath)) {
                unlink($attachmentPath);
            }
        }
    }

    public function testRemovedAttachmentIdIsDetachedOnSave(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $file = new File('materialy.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($file);
        new WorkshopFile($lesson->getMetadata(), $file, WorkshopFileRole::ATTACHMENT, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $file->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('removedAttachmentIds', [$fileId]);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertCount(0, $reloaded->getMetadata()->files);
    }

    public function testChangingAttachmentRoleAndCaptionPersistsOnSave(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $file = new File('regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($file);
        new WorkshopFile($lesson->getMetadata(), $file, WorkshopFileRole::ATTACHMENT, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $file->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('attachmentRoles', [$fileId => 'terms_of_use']);
        $component->set('attachmentCaptions', [$fileId => 'Obowiązuje od 2026']);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        $workshopFile = $reloaded->getMetadata()->files->first();
        static::assertInstanceOf(WorkshopFile::class, $workshopFile);
        static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
        static::assertSame('Obowiązuje od 2026', $workshopFile->getCaption());
    }

    public function testUploadingASecondTermsOfUseAttachmentIsRejectedWithoutCrashing(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $existingTermsFile = new File(
            'regulamin.pdf',
            'application/pdf',
            100,
            str_repeat('a', 64),
            base64_encode('content'),
        );
        $this->em->persist($existingTermsFile);
        new WorkshopFile($lesson->getMetadata(), $existingTermsFile, WorkshopFileRole::TERMS_OF_USE, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $attachmentPath = tempnam(sys_get_temp_dir(), 'workshop-attachment-');
        static::assertNotFalse($attachmentPath);
        file_put_contents($attachmentPath, '%PDF-1.4 another regulamin');

        try {
            $this->client->loginUser($admin);
            $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
                'lessonId' => $lessonId,
                'startOpen' => false,
            ]);

            $component->set('newAttachmentRole', 'terms_of_use');
            $component->call('save', files: [
                'attachmentFiles' => [
                    new UploadedFile($attachmentPath, 'nowy-regulamin.pdf', 'application/pdf', null, true),
                ],
            ]);

            $this->em->clear();
            $reloaded = $this->em->find(Lesson::class, $lessonId);
            static::assertNotNull($reloaded);
            static::assertCount(1, $reloaded->getMetadata()->files);
            $workshopFile = $reloaded->getMetadata()->files->first();
            static::assertInstanceOf(WorkshopFile::class, $workshopFile);
            static::assertSame('regulamin.pdf', $workshopFile->getFile()->getOriginalName());
        } finally {
            if (is_file($attachmentPath)) {
                unlink($attachmentPath);
            }
        }
    }

    public function testAddLibraryFileStagesAnExistingFileForAttachment(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $libraryFile = new File('regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($libraryFile);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $libraryFile->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->call('addLibraryFile', arguments: ['fileId' => $fileId]);

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        static::assertSame([$fileId], $workshopEditorComponent->libraryAttachmentIds);
    }

    public function testSavingWithAStagedLibraryFileAttachesItWithoutStoringItAgain(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $libraryFile = new File('regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($libraryFile);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $libraryFile->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('newAttachmentRole', 'terms_of_use');
        $component->call('addLibraryFile', arguments: ['fileId' => $fileId]);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertCount(1, $reloaded->getMetadata()->files);
        $workshopFile = $reloaded->getMetadata()->files->first();
        static::assertInstanceOf(WorkshopFile::class, $workshopFile);
        // Same File row reused — not a fresh upload.
        static::assertSame($fileId, (string) $workshopFile->getFile()->getId());
        static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
    }

    public function testRemoveLibraryFileUnstagesIt(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $libraryFile = new File('regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($libraryFile);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $libraryFile->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->call('addLibraryFile', arguments: ['fileId' => $fileId]);
        $component->call('removeLibraryFile', arguments: ['fileId' => $fileId]);

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        static::assertSame([], $workshopEditorComponent->libraryAttachmentIds);
    }

    public function testMediaLibrarySearchExcludesFilesAlreadyAttachedToTheCurrentWorkshop(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $attachedFile = new File('regulamin-a.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('a'));
        $otherFile = new File('regulamin-b.pdf', 'application/pdf', 100, str_repeat('b', 64), base64_encode('b'));
        $this->em->persist($attachedFile);
        $this->em->persist($otherFile);
        new WorkshopFile($lesson->getMetadata(), $attachedFile, WorkshopFileRole::ATTACHMENT, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('mediaLibrarySearch', 'regulamin');

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        $resultIds = array_column($workshopEditorComponent->getMediaLibraryResults(), 'id');

        static::assertContains((string) $otherFile->getId(), $resultIds);
        static::assertNotContains((string) $attachedFile->getId(), $resultIds);
    }

    public function testHostCannotEditLessonTheyDoNotInstruct(): void
    {
        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $otherInstructor = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $this->em->persist($host);
        $this->em->persist($otherInstructor);

        $lesson = LessonAssembler::new()->withTitle('Original Title')->assemble();
        $lesson->addInstructor($otherInstructor);
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $this->client->loginUser($host);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('title', 'Hacked Title');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertSame('Original Title', $reloaded->getMetadata()->title);
    }

    public function testInstructorCanEditTheirOwnStandaloneLesson(): void
    {
        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $this->em->persist($host);

        $lesson = LessonAssembler::new()
            ->withTitle('Original Title')
            ->withSchedule(new \DateTimeImmutable('+5 days 10:00'))
            ->assemble();
        $lesson->addInstructor($host);
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $this->client->loginUser($host);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('title', 'Updated By Host');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertSame('Updated By Host', $reloaded->getMetadata()->title);
    }

    public function testOccurrenceScopeEditDoesNotTouchSiblingLessons(): void
    {
        $fixture = $this->buildSeriesWithLessons();
        $admin = $fixture['admin'];
        $current = $fixture['current'];
        $sibling = $fixture['sibling'];

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $current->getId(),
            'startOpen' => false,
        ]);

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        static::assertSame('occurrence', $workshopEditorComponent->editScope);

        $component->set('title', 'Occurrence Only Update');
        $component->call('save');

        $this->em->clear();
        $reloadedCurrent = $this->em->find(Lesson::class, $current->getId());
        $reloadedSibling = $this->em->find(Lesson::class, $sibling->getId());
        static::assertNotNull($reloadedCurrent);
        static::assertNotNull($reloadedSibling);
        static::assertSame('Occurrence Only Update', $reloadedCurrent->getMetadata()->title);
        static::assertSame('Sibling Title', $reloadedSibling->getMetadata()->title);
    }

    public function testSeriesScopeEditPropagatesContentButKeepsEachOccurrenceDate(): void
    {
        $fixture = $this->buildSeriesWithLessons();
        $admin = $fixture['admin'];
        $series = $fixture['series'];
        $current = $fixture['current'];
        $sibling = $fixture['sibling'];
        $pastLesson = $fixture['past'];
        $cancelledLesson = $fixture['cancelled'];

        $siblingOriginalSchedule = $sibling->schedule;

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'seriesId' => $series->getId(),
            'startOpen' => false,
        ]);

        $component->set('editScope', 'series');
        $component->set('title', 'Series Wide Update');
        $component->call('save');

        $this->em->clear();
        $reloadedCurrent = $this->em->find(Lesson::class, $current->getId());
        $reloadedSibling = $this->em->find(Lesson::class, $sibling->getId());
        $reloadedPast = $this->em->find(Lesson::class, $pastLesson->getId());
        $reloadedCancelled = $this->em->find(Lesson::class, $cancelledLesson->getId());

        static::assertNotNull($reloadedCurrent);
        static::assertNotNull($reloadedSibling);
        static::assertNotNull($reloadedPast);
        static::assertNotNull($reloadedCancelled);

        static::assertSame('Series Wide Update', $reloadedCurrent->getMetadata()->title);
        static::assertSame('Series Wide Update', $reloadedSibling->getMetadata()->title);
        static::assertEquals($siblingOriginalSchedule, $reloadedSibling->schedule);

        // Past and cancelled lessons are never touched by a series-wide edit.
        static::assertSame('Past Title', $reloadedPast->getMetadata()->title);
        static::assertSame('Cancelled Title', $reloadedCancelled->getMetadata()->title);
    }

    public function testFollowingScopeEditsCurrentAndLaterLessonsOnly(): void
    {
        $fixture = $this->buildSeriesWithLessons();
        $admin = $fixture['admin'];
        $current = $fixture['current'];
        $sibling = $fixture['sibling'];
        $past = $fixture['past'];

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $current->getId(),
            'startOpen' => false,
        ]);

        $component->set('editScope', 'following');
        $component->set('title', 'Current And Following');
        $component->call('save');

        $this->em->clear();
        static::assertSame('Past Title', $this->em->find(Lesson::class, $past->getId())?->getMetadata()->title);
        static::assertSame(
            'Current And Following',
            $this->em->find(Lesson::class, $current->getId())?->getMetadata()->title,
        );
        static::assertSame(
            'Current And Following',
            $this->em->find(Lesson::class, $sibling->getId())?->getMetadata()->title,
        );
    }

    public function testShorteningSeriesDeletesEmptyLessonsButOnlyHidesLessonsWithReservations(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $customer = UserAssembler::new()->assemble();
        $this->em->persist($admin);
        $this->em->persist($customer);

        $start = new \DateTimeImmutable('+3 days 10:00');
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::WEEKLY)
            ->withLastOccurrenceDate($start->modify('+3 weeks'))
            ->assemble();
        $this->em->persist($series);

        $lessons = [];
        for ($week = 0; $week <= 3; $week++) {
            $lesson = LessonAssembler::new()
                ->withTitle('Finite Series')
                ->withSchedule($start->modify(sprintf('+%d weeks', $week)))
                ->assemble();
            $lesson->setSeries($series);
            $this->em->persist($lesson);
            $lessons[] = $lesson;
        }

        $booking = BookingAssembler::new()->withUser($customer)->withLessons($lessons[2])->assemble();
        $lessons[2]->addBooking($booking);
        $this->em->persist($booking);
        $this->em->flush();
        $protectedId = $lessons[2]->getId();
        $emptyId = $lessons[3]->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessons[0]->getId(),
            'startOpen' => false,
        ]);
        $component->set('endDate', $start->modify('+1 week')->format('Y-m-d'));
        $component->call('save');

        $this->em->clear();
        $protected = $this->em->find(Lesson::class, $protectedId);
        static::assertNotNull($protected);
        static::assertFalse($protected->visible);
        static::assertNull($this->em->find(Lesson::class, $emptyId));
    }

    public function testSaveNotifiesOtherInstructorsAndAdminsButExcludesTheEditor(): void
    {
        $editorHost = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $otherInstructor = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($editorHost);
        $this->em->persist($otherInstructor);
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()
            ->withTitle('Original Title')
            ->withSchedule(new \DateTimeImmutable('+3 days 09:00'))
            ->assemble();
        $lesson->addInstructor($editorHost);
        $lesson->addInstructor($otherInstructor);
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $this->client->loginUser($editorHost);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('title', 'Notified Update');
        $component->call('save');

        $editorNotifications = $this->notificationRepository->findRecentForUser($editorHost);
        $otherInstructorNotifications = $this->notificationRepository->findRecentForUser($otherInstructor);
        $adminNotifications = $this->notificationRepository->findRecentForUser($admin);

        static::assertCount(0, $editorNotifications);
        static::assertCount(1, $otherInstructorNotifications);
        static::assertCount(1, $adminNotifications);
    }

    /**
     * @return array{admin: User, series: Series, current: Lesson, sibling: Lesson, past: Lesson, cancelled: Lesson}
     */
    private function buildSeriesWithLessons(): array
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $series = SeriesAssembler::new()->withType(WorkshopType::WEEKLY)->assemble();
        $this->em->persist($series);

        $current = LessonAssembler::new()
            ->withTitle('Current Title')
            ->withSchedule(new \DateTimeImmutable('+3 days 10:00'))
            ->assemble();
        $current->setSeries($series);
        $this->em->persist($current);

        $sibling = LessonAssembler::new()
            ->withTitle('Sibling Title')
            ->withSchedule(new \DateTimeImmutable('+10 days 10:00'))
            ->assemble();
        $sibling->setSeries($series);
        $this->em->persist($sibling);

        $pastLesson = LessonAssembler::new()
            ->withTitle('Past Title')
            ->withSchedule(new \DateTimeImmutable('-5 days 10:00'))
            ->assemble();
        $pastLesson->setSeries($series);
        $this->em->persist($pastLesson);

        $cancelledLesson = LessonAssembler::new()
            ->withTitle('Cancelled Title')
            ->withStatus('cancelled')
            ->withSchedule(new \DateTimeImmutable('+7 days 10:00'))
            ->assemble();
        $cancelledLesson->setSeries($series);
        $this->em->persist($cancelledLesson);

        $this->em->flush();

        return [
            'admin' => $admin,
            'series' => $series,
            'current' => $current,
            'sibling' => $sibling,
            'past' => $pastLesson,
            'cancelled' => $cancelledLesson,
        ];
    }
}

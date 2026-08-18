<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Component\WorkshopEditorComponent;
use App\Entity\Lesson;
use App\Entity\Series;
use App\Entity\User;
use App\Entity\WorkshopType;
use App\Repository\NotificationRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
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
        self::assertSame('Błotna Kuchnia', $workshopEditorComponent->title);
        self::assertSame('2030-06-15', $workshopEditorComponent->occurrenceDate);
        self::assertSame('10:30', $workshopEditorComponent->occurrenceTime);
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
        self::assertSame('50.00', $workshopEditorComponent->singleTicketPrice);
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
        self::assertNotNull($reloaded);
        $ticketOption = $reloaded->getTicketOptions()[0] ?? null;
        self::assertNotNull($ticketOption);
        self::assertTrue($ticketOption->price->isEqualTo(Money::of('75.50', 'PLN')));
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
        self::assertNotNull($reloaded);
        self::assertNotSame('Should Not Persist', $reloaded->getMetadata()->title);
        $ticketOption = $reloaded->getTicketOptions()[0] ?? null;
        self::assertNotNull($ticketOption);
        self::assertTrue($ticketOption->price->isEqualTo(Money::of('50.00', 'PLN')));
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
        self::assertNotFalse($imagePath);
        $imageData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($imageData);
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
            self::assertNotNull($reloaded);
            self::assertSame('image/png', $reloaded->getMetadata()->imageMimeType);
            self::assertSame(base64_encode($imageData), $reloaded->getMetadata()->imageData);
        } finally {
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }
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
        self::assertNotNull($reloaded);
        self::assertSame('Original Title', $reloaded->getMetadata()->title);
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
        self::assertNotNull($reloaded);
        self::assertSame('Updated By Host', $reloaded->getMetadata()->title);
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
        self::assertSame('occurrence', $workshopEditorComponent->editScope);

        $component->set('title', 'Occurrence Only Update');
        $component->call('save');

        $this->em->clear();
        $reloadedCurrent = $this->em->find(Lesson::class, $current->getId());
        $reloadedSibling = $this->em->find(Lesson::class, $sibling->getId());
        self::assertNotNull($reloadedCurrent);
        self::assertNotNull($reloadedSibling);
        self::assertSame('Occurrence Only Update', $reloadedCurrent->getMetadata()->title);
        self::assertSame('Sibling Title', $reloadedSibling->getMetadata()->title);
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

        self::assertNotNull($reloadedCurrent);
        self::assertNotNull($reloadedSibling);
        self::assertNotNull($reloadedPast);
        self::assertNotNull($reloadedCancelled);

        self::assertSame('Series Wide Update', $reloadedCurrent->getMetadata()->title);
        self::assertSame('Series Wide Update', $reloadedSibling->getMetadata()->title);
        self::assertEquals($siblingOriginalSchedule, $reloadedSibling->schedule);

        // Past and cancelled lessons are never touched by a series-wide edit.
        self::assertSame('Past Title', $reloadedPast->getMetadata()->title);
        self::assertSame('Cancelled Title', $reloadedCancelled->getMetadata()->title);
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

        self::assertCount(0, $editorNotifications);
        self::assertCount(1, $otherInstructorNotifications);
        self::assertCount(1, $adminNotifications);
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

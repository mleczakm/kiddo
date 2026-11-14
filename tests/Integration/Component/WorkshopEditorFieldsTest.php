<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\Lesson;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\WorkshopEditorComponent;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class WorkshopEditorFieldsTest extends WorkshopEditorComponentTestCase
{
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
}

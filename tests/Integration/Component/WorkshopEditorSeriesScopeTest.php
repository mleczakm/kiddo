<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\Lesson;
use App\Entity\WorkshopType;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\WorkshopEditorComponent;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class WorkshopEditorSeriesScopeTest extends WorkshopEditorComponentTestCase
{
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
}

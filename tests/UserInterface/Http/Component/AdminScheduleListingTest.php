<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\WorkshopType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminScheduleComponent;
use Brick\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class AdminScheduleListingTest extends AdminScheduleComponentTestCase
{
    public function testEmptyStateShowsMessage(): void
    {
        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client);
        $html = (string) $component->render();

        static::assertStringContainsString('Harmonogram', $html);
        static::assertStringContainsString('Brak zajęć w wybranym tygodniu', $html);
    }

    public function testDisplaysSeriesWithTicketsPeriodTypeAndStatus(): void
    {
        $weekStart = new \DateTimeImmutable('2025-01-06');

        // Create a Series with two lessons in this week
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, [
            new TicketOption(
                TicketType::ONE_TIME,
                Money::of(50, 'PLN'),
                'Bilet jednorazowy',
                TicketReschedulePolicy::UNLIMITED_24H_BEFORE,
            ),
            new TicketOption(
                TicketType::CARNET_4,
                Money::of(180, 'PLN'),
                'Karnet 4',
                TicketReschedulePolicy::ONETIME_24H_BEFORE,
            ),
        ]);
        $this->em->persist($series);

        $l1 = $this->createLesson('Series L1', $weekStart->modify('+1 day'));
        $l2 = $this->createLesson('Series L2', $weekStart->modify('+3 days'));
        $l1->setSeries($series);
        $l2->setSeries($series);

        $this->em->persist($l1);
        $this->em->persist($l2);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('Harmonogram', $html);
        // Type label (weekly)
        static::assertStringContainsString('Cotygodniowa', $html);
        // Both occurrences render nested under the series
        static::assertStringContainsString($weekStart->modify('+1 day')->format('d.m.Y'), $html);
        static::assertStringContainsString($weekStart->modify('+3 days')->format('d.m.Y'), $html);
        // Status badge Active
        static::assertStringContainsString('Aktywne', $html);
        // Ticket type tokens (by enum value)
        static::assertStringContainsString('jednorazowy', $html);
        static::assertStringContainsString('karnet 4 wejścia', $html);
    }

    public function testLessonWithoutSeriesRendersAsOwnRow(): void
    {
        $weekStart = new \DateTimeImmutable('2025-04-07');

        $standalone = $this->createLesson('Standalone Lesson', $weekStart->modify('+1 day'));
        $this->em->persist($standalone);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('Standalone Lesson', $html);
    }

    public function testCancelledSeriesHiddenByDefault(): void
    {
        $weekStart = new \DateTimeImmutable('2025-05-05');
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, [], 'cancelled');
        $this->em->persist($series);

        $l1 = $this->createLesson('Hidden Series Lesson', $weekStart->modify('+1 day'));
        $l1->setSeries($series);
        $this->em->persist($l1);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringNotContainsString('Hidden Series Lesson', $html);
    }

    public function testWeekFilteringOnlyShowsSelectedWeek(): void
    {
        $weekStart = new \DateTimeImmutable('2025-02-10');
        $prevWeek = $weekStart->modify('-7 days');

        $inWeek = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($weekStart->modify('+1 day'))
            ->withTitle('In Week Lesson')
            ->withStatus('active')
            ->assemble();
        $outOfWeek = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($prevWeek->modify('+1 day'))
            ->withTitle('Out Lesson')
            ->withStatus('active')
            ->assemble();

        $this->em->persist($inWeek);
        $this->em->persist($outOfWeek);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('In Week Lesson', $html);
        static::assertStringNotContainsString('Out Lesson', $html);
    }

    public function testHostOnlySeesLessonsTheyInstruct(): void
    {
        $weekStart = new \DateTimeImmutable('2025-03-03');

        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $this->em->persist($host);

        $ownLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($weekStart->modify('+1 day'))
            ->withTitle('Own Lesson')
            ->withStatus('active')
            ->assemble();
        $ownLesson->addInstructor($host);

        $otherLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->assemble())
            ->withSchedule($weekStart->modify('+2 days'))
            ->withTitle('Other Lesson')
            ->withStatus('active')
            ->assemble();

        $this->em->persist($ownLesson);
        $this->em->persist($otherLesson);
        $this->em->flush();

        $this->client->loginUser($host);

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('Own Lesson', $html);
        static::assertStringNotContainsString('Other Lesson', $html);
    }
}

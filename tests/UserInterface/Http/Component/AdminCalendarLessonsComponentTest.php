<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\UserInterface\Http\Component\AdminCalendarLessonsComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminCalendarLessonsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testEmptyStateWhenNoUpcomingLessons(): void
    {
        Clock::set(new MockClock('2025-03-15 08:00:00'));
        $client = static::createClient();

        $component = $this->createLiveComponent(name: AdminCalendarLessonsComponent::class, client: $client);

        self::assertSame([], $component->component()->getCalendarDays());
    }

    public function testUpcomingLessonsAreGroupedByDayWithRealData(): void
    {
        Clock::set(new MockClock('2025-03-15 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $sameDayLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()
                ->withTitle('Sensoplastyka dla maluchów')
                ->withSchedule(new \DateTimeImmutable('2025-03-16 10:00:00'))
                ->withDuration(90)
                ->withCapacity(10)
                ->assemble())
            ->withStatus('active')
            ->assemble();

        $laterLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()
                ->withTitle('Klub Malucha')
                ->withSchedule(new \DateTimeImmutable('2025-03-17 09:00:00'))
                ->withDuration(60)
                ->withCapacity(8)
                ->assemble())
            ->withStatus('active')
            ->assemble();

        // In the past -> excluded entirely
        $pastLesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()
                ->withTitle('Stare zajęcia')
                ->withSchedule(new \DateTimeImmutable('2025-03-01 09:00:00'))
                ->assemble())
            ->withStatus('active')
            ->assemble();

        $em->persist($sameDayLesson);
        $em->persist($laterLesson);
        $em->persist($pastLesson);
        $em->flush();

        $component = $this->createLiveComponent(name: AdminCalendarLessonsComponent::class, client: $client);
        $days = $component->component()
            ->getCalendarDays();

        self::assertCount(2, $days);
        self::assertSame('2025-03-16', $days[0]['date']->format('Y-m-d'));
        self::assertSame('2025-03-17', $days[1]['date']->format('Y-m-d'));

        self::assertSame('10:00 - 11:30', $days[0]['lessons'][0]['time']);
        self::assertSame('Sensoplastyka dla maluchów', $days[0]['lessons'][0]['title']);
        self::assertSame('0/10', $days[0]['lessons'][0]['capacity']);
        self::assertSame('Odbędzie się', $days[0]['lessons'][0]['status']);

        $html = (string) $component->render();
        self::assertStringContainsString('Sensoplastyka dla maluchów', $html);
        self::assertStringContainsString('Klub Malucha', $html);
        self::assertStringNotContainsString('Stare zajęcia', $html);
    }
}

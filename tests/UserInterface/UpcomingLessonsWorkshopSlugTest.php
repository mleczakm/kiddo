<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

use App\Entity\Lesson;
use App\Entity\WorkshopType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\SeriesAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

#[Group('functional')]
final class UpcomingLessonsWorkshopSlugTest extends WebTestCase
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testHomepageRendersWorkshopSlugLinksForUpcomingLessons(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $schedule = new \DateTimeImmutable('2024-02-21 10:30:00');
        $lesson = $this->persistLessonWithSeries($em, 'Bałaganki', $schedule);

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href*="/warsztaty/balaganki"]');
        $this->assertSelectorExists('a[href*="date=2024-02-21"]');
        $this->assertSelectorExists('a[href*="hour=10"]');
    }

    public function testHomepageDoesNotFailWhenLessonSlugIsMissing(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $schedule = new \DateTimeImmutable('2024-02-21 10:30:00');
        $lesson = $this->persistLessonWithSeries($em, 'Senso Bobasy', $schedule);

        // Simulate legacy rows that still have an empty slug in the database.
        $em->getConnection()
            ->update('lesson', [
                'slug' => '',
            ], [
                'id' => $lesson->getId()
                    ->toRfc4122(),
            ]);
        $em->clear();

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()
            ->getContent();
        $this->assertStringContainsString('/warsztaty?week=2024-02-21', $content);
        $this->assertStringNotContainsString('/warsztaty/senso-bobasy', $content);
    }

    public function testWorkshopBySlugOpensMatchingModal(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $schedule = new \DateTimeImmutable('2024-02-21 10:30:00');
        $this->persistLessonWithSeries($em, 'Bałaganki', $schedule);

        $client->request('GET', '/warsztaty/balaganki?date=2024-02-21&hour=10:30');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-modal-state="open"]');
        $this->assertStringContainsString('Bałaganki', (string) $client->getResponse()->getContent());
    }

    private function persistLessonWithSeries(
        EntityManagerInterface $em,
        string $title,
        \DateTimeImmutable $schedule,
    ): Lesson {
        $series = SeriesAssembler::new()
            ->withType(WorkshopType::WEEKLY)
            ->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(
                LessonMetadataAssembler::new()
                    ->withTitle($title)
                    ->withSchedule($schedule)
                    ->assemble()
            )
            ->assemble();
        $lesson->setSeries($series);

        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        return $lesson;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use Brick\Money\Money;
use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\WorkshopType;
use App\Repository\SeriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use App\UserInterface\Http\Component\AdminScheduleComponent;

#[Group('functional')]
final class AdminScheduleComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testEmptyStateShowsMessage(): void
    {
        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client);
        $html = (string) $component->render();

        self::assertStringContainsString('Harmonogram', $html);
        self::assertStringContainsString('Brak serii w wybranym tygodniu', $html);
    }

    public function testDisplaysSeriesWithTicketsPeriodTypeAndStatus(): void
    {
        $weekStart = new \DateTimeImmutable('2025-01-06');

        // Create a Series with two lessons in this week
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, [
            new TicketOption(TicketType::ONE_TIME, Money::of(
                50,
                'PLN'
            ), 'Bilet jednorazowy', TicketReschedulePolicy::UNLIMITED_24H_BEFORE),
            new TicketOption(TicketType::CARNET_4, Money::of(
                180,
                'PLN'
            ), 'Karnet 4', TicketReschedulePolicy::ONETIME_24H_BEFORE),
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

        self::assertStringContainsString('Harmonogram', $html);
        // Type label (weekly)
        self::assertStringContainsString('Cotygodniowa', $html);
        // Period dates present
        self::assertStringContainsString($weekStart->modify('+1 day')->format('Y-m-d'), $html);
        self::assertStringContainsString($weekStart->modify('+3 days')->format('Y-m-d'), $html);
        // Status badge Active
        self::assertStringContainsString('Aktywne', $html);
        // Ticket type tokens (by enum value)
        self::assertStringContainsString('jednorazowy', $html);
        self::assertStringContainsString('karnet 4 wejścia', $html);
    }

    public function testCancelSeriesActionChangesStatus(): void
    {
        $weekStart = new \DateTimeImmutable('2025-01-06');
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY, []);

        $this->em->persist($series);
        $l1 = $this->createLesson('Series L1', $weekStart->modify('+1 day'));
        $l1->setSeries($series);
        $this->em->persist($l1);
        $this->em->flush();

        $component = $this->createLiveComponent(name: AdminScheduleComponent::class, client: $this->client, data: [
            'week' => $weekStart->format('Y-m-d'),
            'showCancelled' => true,
        ]);

        // Cancel
        $component->call('cancelSeries', [
            'seriesId' => (string) $series->getId(),
        ]);

        // Reload from DB and assert status
        /** @var SeriesRepository $repo */
        $repo = self::getContainer()->get(SeriesRepository::class);
        $reloaded = $repo->find($series->getId());

        self::assertNotNull($reloaded);
        self::assertSame('cancelled', $reloaded->status);


        $html = (string) $component->render();
        self::assertStringContainsString('Anulowane', $html);
    }

    private function createLesson(string $title, \DateTimeImmutable $schedule): Lesson
    {
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Lead',
            visualTheme: 'default',
            description: 'Desc',
            capacity: 10,
            schedule: $schedule,
            duration: 60,
            ageRange: new AgeRange(3, 8),
            category: 'test'
        );
        return new Lesson($metadata);
    }
}

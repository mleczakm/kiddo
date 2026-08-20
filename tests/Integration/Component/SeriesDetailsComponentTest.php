<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\Child;
use App\Entity\Series;
use App\Entity\WorkshopType;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\SeriesDetailsComponent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class SeriesDetailsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);
    }

    public function testExpandLessonAcceptsBrowserStringArgumentAndTogglesDetails(): void
    {
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $lesson = LessonAssembler::new()
            ->withTitle('Interactive preview lesson')
            ->withSchedule(new \DateTimeImmutable('+2 days 10:00'))
            ->assemble();
        $lesson->setSeries($series);
        $this->em->persist($series);
        $this->em->persist($lesson);
        $this->em->flush();

        $component = $this->createLiveComponent(name: 'SeriesDetails', client: $this->client, data: [
            'seriesId' => $series->getId(),
        ]);

        $component->call('expandLesson', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        /** @var SeriesDetailsComponent $state */
        $state = $component->component();
        static::assertTrue($state->expandedLessonId?->equals($lesson->getId()));
        static::assertStringContainsString('Lista obecności', (string) $component->render());

        $component->call('expandLesson', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        /** @var SeriesDetailsComponent $collapsedState */
        $collapsedState = $component->component();
        static::assertNull($collapsedState->expandedLessonId);
    }

    public function testRenderedActionUsesLiveArgumentParameterName(): void
    {
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $lesson = LessonAssembler::new()->withSchedule(new \DateTimeImmutable('+2 days 10:00'))->assemble();
        $lesson->setSeries($series);
        $this->em->persist($series);
        $this->em->persist($lesson);
        $this->em->flush();

        $component = $this->createLiveComponent(name: 'SeriesDetails', client: $this->client, data: [
            'seriesId' => $series->getId(),
        ]);
        $html = (string) $component->render();

        static::assertStringContainsString('data-live-action-param="expandLesson"', $html);
        static::assertStringContainsString('data-live-lesson-id-param="' . $lesson->getId() . '"', $html);
        static::assertStringContainsString('href="/admin/zajecia/' . $lesson->getId() . '"', $html);
        static::assertStringContainsString('aria-label="Otwórz szczegóły zajęć"', $html);
        static::assertStringNotContainsString('data-live-action-param-lessonId', $html);
    }

    public function testExpandedLessonRendersReservationWithChildBirthday(): void
    {
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $lesson = LessonAssembler::new()
            ->withTitle('Workshop with reservation')
            ->withSchedule(new \DateTimeImmutable('+2 days 10:00'))
            ->assemble();
        $lesson->setSeries($series);

        $parent = UserAssembler::new()->withPhone('+48 501 111 111')->assemble();
        $child = new Child($parent, 'Zosia', new \DateTimeImmutable('2020-04-12'));
        $booking = BookingAssembler::new()->withUser($parent)->withChild($child)->withLessons($lesson)->assemble();

        $this->em->persist($series);
        $this->em->persist($lesson);
        $this->em->persist($parent);
        $this->em->persist($child);
        $this->em->persist($booking);
        $this->em->flush();

        $component = $this->createLiveComponent(name: 'SeriesDetails', client: $this->client, data: [
            'seriesId' => $series->getId(),
        ]);
        $component->call('expandLesson', [
            'lessonId' => (string) $lesson->getId(),
        ]);

        $html = (string) $component->render();
        static::assertStringContainsString('Zosia', $html);
        static::assertStringContainsString('(2020)', $html);
        static::assertStringContainsString($parent->getName(), $html);
        static::assertStringContainsString('501 111 111', $html);
        static::assertStringNotContainsString('Country Code:', $html);
    }
}

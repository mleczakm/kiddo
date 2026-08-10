<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

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
final class WorkshopsPageResponseSizeTest extends WebTestCase
{
    private const int SWOOLE_DEFAULT_OUTPUT_BUFFER = 2_097_152;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testWorkshopsPageStaysBelowSwooleDefaultOutputBuffer(): void
    {
        Clock::set(new MockClock('2024-02-20 08:00:00'));

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $series = SeriesAssembler::new()
            ->withType(WorkshopType::WEEKLY)
            ->assemble();
        $em->persist($series);

        for ($i = 0; $i < 40; ++$i) {
            $lesson = LessonAssembler::new()
                ->withMetadata(
                    LessonMetadataAssembler::new()
                        ->withTitle(sprintf('Workshop %d', $i))
                        ->withDescription(str_repeat('Long description for size pressure. ', 20))
                        ->withTitle(sprintf('Workshop %d', $i))
                        ->withDescription(str_repeat('Long description for size pressure. ', 20))
                        ->assemble()
                )
                ->withSchedule(new \DateTimeImmutable(sprintf('2024-02-21 %02d:00:00', 8 + ($i % 10))))
                ->assemble();
            $lesson->setSeries($series);
            $em->persist($lesson);
        }
        $em->flush();

        $client->request('GET', '/warsztaty');

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()
            ->getContent();

        $this->assertLessThan(
            self::SWOOLE_DEFAULT_OUTPUT_BUFFER,
            strlen($content),
            'Closed LessonModal markup must stay deferred so /warsztaty fits Swoole output buffer'
        );
        $this->assertStringContainsString('data-modal-state="closed"', $content);
        $this->assertStringNotContainsString('data-modal-target="dialog"', $content);
    }
}

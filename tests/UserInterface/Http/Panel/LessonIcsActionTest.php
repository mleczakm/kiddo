<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Panel;

use App\Entity\Lesson;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class LessonIcsActionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface, 'test container provides the ORM entity manager');
        $this->em = $em;
    }

    public function testAnonymousCannotDownload(): void
    {
        $this->client->request('GET', '/panel/zajecia/' . (string) new Ulid() . '.ics');

        static::assertContains($this->client->getResponse()->getStatusCode(), [301, 302, 401, 403]);
    }

    public function testReturnsNotFoundWhenTheUserHasNoBookingForTheLesson(): void
    {
        $user = UserAssembler::new()->withEmail('nobooking@example.com')->assemble();
        $lesson = $this->persistLesson('Sensoryka');
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/panel/zajecia/' . (string) $lesson->getId() . '.ics');

        static::assertResponseStatusCodeSame(404);
    }

    public function testServesTheCalendarForABookedLesson(): void
    {
        $user = UserAssembler::new()->withEmail('booked@example.com')->assemble();
        $lesson = $this->persistLesson('Sensoryka');
        $booking = BookingAssembler::new()->withUser($user)->withLessons($lesson)->withStatus('confirmed')->assemble();
        $lesson->addBooking($booking);
        $this->em->persist($user);
        $this->em->persist($booking);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/panel/zajecia/' . (string) $lesson->getId() . '.ics');

        static::assertResponseIsSuccessful();
        static::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        static::assertStringContainsString(
            'attachment; filename="lekcja-',
            (string) $this->client->getResponse()->headers->get('Content-Disposition'),
        );
        $body = (string) $this->client->getResponse()->getContent();
        static::assertStringContainsString('BEGIN:VCALENDAR', $body);
        static::assertStringContainsString('SUMMARY:Sensoryka', $body);
        static::assertStringContainsString('LOCATION:', $body);
    }

    private function persistLesson(string $title): Lesson
    {
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle($title)->assemble())
            ->withSchedule(Clock::get()->now()->modify('+7 days'))
            ->assemble();
        $this->em->persist($lesson);

        return $lesson;
    }
}

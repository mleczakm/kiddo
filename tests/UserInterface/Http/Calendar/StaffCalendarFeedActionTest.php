<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Calendar;

use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class StaffCalendarFeedActionTest extends WebTestCase
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

    public function testStaffTokenReturnsSubscribableCalendar(): void
    {
        $host = UserAssembler::new()->withEmail('host@example.com')->withRoles('ROLE_HOST')->assemble();
        $token = $host->regenerateCalendarFeedToken();
        $this->em->persist($host);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Sensoryka')->assemble())
            ->withSchedule(Clock::get()->now()->modify('+5 days'))
            ->assemble();
        $this->em->persist($lesson);
        $this->em->flush();

        $this->client->request('GET', "/kalendarz/{$token}/wszystkie.ics");

        static::assertResponseIsSuccessful();
        static::assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        $body = (string) $this->client->getResponse()->getContent();
        static::assertStringContainsString('BEGIN:VCALENDAR', $body);
        static::assertStringContainsString('X-WR-CALNAME:', $body);
        static::assertStringContainsString('SUMMARY:Sensoryka', $body);
        static::assertStringContainsString('STATUS:CONFIRMED', $body);
    }

    public function testUnknownTokenIs404(): void
    {
        $this->client->request('GET', '/kalendarz/' . str_repeat('a', 40) . '/wszystkie.ics');

        static::assertResponseStatusCodeSame(404);
    }

    public function testTokenOfANonStaffUserIs404(): void
    {
        $customer = UserAssembler::new()->withEmail('customer@example.com')->assemble();
        $token = $customer->regenerateCalendarFeedToken();
        $this->em->persist($customer);
        $this->em->flush();

        $this->client->request('GET', "/kalendarz/{$token}/wszystkie.ics");

        static::assertResponseStatusCodeSame(404);
    }

    public function testMineScopeExcludesLessonsTheUserDoesNotInstruct(): void
    {
        $host = UserAssembler::new()->withEmail('host2@example.com')->withRoles('ROLE_HOST')->assemble();
        $token = $host->regenerateCalendarFeedToken();
        $this->em->persist($host);

        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Nie moje zajęcia')->assemble())
            ->withSchedule(Clock::get()->now()->modify('+5 days'))
            ->assemble();
        $this->em->persist($lesson);
        $this->em->flush();

        $this->client->request('GET', "/kalendarz/{$token}/moje.ics");

        static::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        static::assertStringContainsString('BEGIN:VCALENDAR', $body);
        static::assertStringNotContainsString('Nie moje zajęcia', $body);
    }
}

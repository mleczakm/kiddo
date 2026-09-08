<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\ToolResult;
use App\Entity\Booking;
use App\Entity\User;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SettingAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class StaffChatToolsTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private ChatToolRegistry $registry;

    private int $hostId;

    private string $bobasyId;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        $registry = self::getContainer()->get(ChatToolRegistry::class);
        static::assertInstanceOf(ChatToolRegistry::class, $registry);
        $this->registry = $registry;

        $host = UserAssembler::new()->withName('Henryk Prowadzący')->withRoles('ROLE_HOST')->assemble();
        $p1 = UserAssembler::new()->withName('Anna Kowalska')->withEmail('anna@example.com')->assemble();
        $p2 = UserAssembler::new()->withName('Bartek Nowak')->withEmail('bartek@example.com')->assemble();
        $p3 = UserAssembler::new()->withName('Cezary Wójcik')->withEmail('cezary@example.com')->assemble();

        $bobasy = LessonAssembler::new()
            ->withTitle('Senso bobasy')
            ->withSchedule(Clock::get()->now()->modify('+2 days'))
            ->withCapacity(10)
            ->assemble();
        $rytmika = LessonAssembler::new()
            ->withTitle('Rytmika dla smyka')
            ->withSchedule(Clock::get()->now()->modify('+3 days'))
            ->withCapacity(10)
            ->assemble();
        $rytmika->addInstructor($host);

        $active1 = BookingAssembler::new()
            ->withUser($p1)
            ->withPayment(null)
            ->withLessons($bobasy)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $active2 = BookingAssembler::new()
            ->withUser($p2)
            ->withPayment(null)
            ->withLessons($bobasy)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->assemble();
        $cancelled = BookingAssembler::new()
            ->withUser($p3)
            ->withPayment(null)
            ->withLessons($bobasy)
            ->withStatus(Booking::STATUS_CANCELLED)
            ->assemble();
        foreach ([$active1, $active2, $cancelled] as $booking) {
            $bobasy->addBooking($booking);
        }

        $organizationDetails = SettingAssembler::new()->asOrganizationDetails()->assemble();

        foreach ([
            $host,
            $p1,
            $p2,
            $p3,
            $bobasy,
            $rytmika,
            $active1,
            $active2,
            $cancelled,
            $organizationDetails,
        ] as $e) {
            $this->em->persist($e);
        }
        $this->em->flush();

        $this->hostId = (int) $host->getId();
        $this->bobasyId = (string) $bobasy->getId();
        $this->em->clear();
    }

    private function hostActor(): ChatActor
    {
        $host = $this->em->find(User::class, $this->hostId);
        static::assertInstanceOf(User::class, $host);

        return new ChatActor($host, ['ROLE_HOST']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(ToolResult $result, string $key): array
    {
        static::assertArrayHasKey($key, $result->data);
        static::assertIsArray($result->data[$key]);

        /** @var list<array<string, mixed>> */
        return $result->data[$key];
    }

    private function intData(ToolResult $result, string $key): int
    {
        static::assertArrayHasKey($key, $result->data);
        static::assertIsInt($result->data[$key]);

        return $result->data[$key];
    }

    public function testLessonParticipantsResolvedByFuzzyNameExcludesCancelled(): void
    {
        $result = $this->registry->call('staff.lesson_participants', $this->hostActor(), [
            'query' => 'bobas',
            'when' => 'next',
        ]);

        static::assertTrue($result->ok, $result->error ?? '');
        static::assertStringContainsString('Senso bobasy', $result->summary);
        static::assertSame(2, $this->intData($result, 'count'));
        $emails = array_column($this->rows($result, 'participants'), 'email');
        sort($emails);
        static::assertSame(['anna@example.com', 'bartek@example.com'], $emails);
    }

    public function testLessonParticipantsCanIncludeCancelled(): void
    {
        $result = $this->registry->call('staff.lesson_participants', $this->hostActor(), [
            'query' => 'bobas',
            'when' => 'next',
            'include_cancelled' => true,
        ]);

        static::assertTrue($result->ok, $result->error ?? '');
        static::assertSame(3, $this->intData($result, 'count'));
    }

    public function testLessonParticipantsByLessonId(): void
    {
        $result = $this->registry->call('staff.lesson_participants', $this->hostActor(), [
            'lesson_id' => $this->bobasyId,
        ]);

        static::assertTrue($result->ok, $result->error ?? '');
        static::assertSame(2, $this->intData($result, 'count'));
    }

    public function testFindLessonsFuzzyMatchesAnyLesson(): void
    {
        $result = $this->registry->call('staff.find_lessons', $this->hostActor(), [
            'query' => 'bob',
        ]);

        static::assertTrue($result->ok, $result->error ?? '');
        $lessons = $this->rows($result, 'lessons');
        static::assertContains('Senso bobasy', array_column($lessons, 'title'));
        $bobasyRow = array_values(array_filter(
            $lessons,
            static fn(array $row): bool => $row['title'] === 'Senso bobasy',
        ))[0];
        static::assertFalse($bobasyRow['is_mine']);
        static::assertSame(2, $bobasyRow['active_participants']);
    }

    public function testFindLessonsScopeMineOnlyReturnsOwnLessons(): void
    {
        $result = $this->registry->call('staff.find_lessons', $this->hostActor(), [
            'scope' => 'mine',
        ]);

        static::assertTrue($result->ok, $result->error ?? '');
        $lessons = $this->rows($result, 'lessons');
        static::assertSame(['Rytmika dla smyka'], array_column($lessons, 'title'));
        static::assertTrue($lessons[0]['is_mine']);
    }

    public function testLessonParticipantsWhenLastPicksMostRecentPastOccurrence(): void
    {
        $older = LessonAssembler::new()
            ->withTitle('Senso bobasy')
            ->withSchedule(Clock::get()->now()->modify('-10 days'))
            ->assemble();
        $recent = LessonAssembler::new()
            ->withTitle('Senso bobasy')
            ->withSchedule(Clock::get()->now()->modify('-2 days'))
            ->assemble();
        foreach ([$older, $recent] as $lesson) {
            $this->em->persist($lesson);
        }
        $this->em->flush();
        $recentId = (string) $recent->getId();
        $this->em->clear();

        $result = $this->registry->call('staff.lesson_participants', $this->hostActor(), [
            'query' => 'bobas',
            'when' => 'last',
        ]);

        static::assertTrue($result->ok, $result->error ?? '');
        static::assertArrayHasKey('id', $result->data);
        static::assertSame($recentId, $result->data['id']);
    }

    public function testParentIsDeniedStaffTools(): void
    {
        $parentUser = UserAssembler::new()->withName('Rodzic')->withRoles('ROLE_USER')->assemble();
        $this->em->persist($parentUser);
        $this->em->flush();

        $result = $this->registry->call('staff.find_lessons', new ChatActor($parentUser, ['ROLE_USER']), [
            'query' => 'bob',
        ]);

        static::assertFalse($result->ok);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Tests\Assembler\LessonAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class StaffLessonParticipantsTest extends StaffChatToolsTestCase
{
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
}

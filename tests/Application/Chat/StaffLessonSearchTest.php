<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class StaffLessonSearchTest extends StaffChatToolsTestCase
{
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

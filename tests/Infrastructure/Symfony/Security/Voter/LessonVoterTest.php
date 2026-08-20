<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony\Security\Voter;

use App\Entity\Series;
use App\Entity\WorkshopType;
use App\Infrastructure\Symfony\Security\Voter\LessonVoter;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[Group('unit')]
final class LessonVoterTest extends TestCase
{
    public function testAdminIsAlwaysGranted(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $voter = new LessonVoter($security);
        $lesson = LessonAssembler::new()->assemble();
        $token = $this->createMock(TokenInterface::class);

        $result = $voter->vote($token, $lesson, [LessonVoter::VIEW]);

        static::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testHostAssignedAsInstructorIsGranted(): void
    {
        $host = UserAssembler::new()->withId(42)->withRoles('ROLE_HOST')->assemble();

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnMap([['ROLE_ADMIN', false], ['ROLE_HOST', true]]);

        $voter = new LessonVoter($security);
        $lesson = LessonAssembler::new()->assemble();
        $lesson->addInstructor($host);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($host);

        $result = $voter->vote($token, $lesson, [LessonVoter::VIEW]);

        static::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testHostNotAssignedIsDenied(): void
    {
        $host = UserAssembler::new()->withId(42)->withRoles('ROLE_HOST')->assemble();
        $otherInstructor = UserAssembler::new()->withId(99)->withRoles('ROLE_HOST')->assemble();

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnMap([['ROLE_ADMIN', false], ['ROLE_HOST', true]]);

        $voter = new LessonVoter($security);
        $lesson = LessonAssembler::new()->assemble();
        $lesson->addInstructor($otherInstructor);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($host);

        $result = $voter->vote($token, $lesson, [LessonVoter::VIEW]);

        static::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testHostAssignedViaSeriesIsGranted(): void
    {
        $host = UserAssembler::new()->withId(7)->withRoles('ROLE_HOST')->assemble();

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnMap([['ROLE_ADMIN', false], ['ROLE_HOST', true]]);

        $voter = new LessonVoter($security);
        $lesson = LessonAssembler::new()->assemble();
        $series = new Series(new ArrayCollection(), WorkshopType::WEEKLY);
        $series->addInstructor($host);
        $lesson->setSeries($series);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($host);

        $result = $voter->vote($token, $lesson, [LessonVoter::VIEW]);

        static::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testPlainUserIsDenied(): void
    {
        $user = UserAssembler::new()->withId(1)->assemble();

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnMap([['ROLE_ADMIN', false], ['ROLE_HOST', false]]);

        $voter = new LessonVoter($security);
        $lesson = LessonAssembler::new()->assemble();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $voter->vote($token, $lesson, [LessonVoter::VIEW]);

        static::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }
}

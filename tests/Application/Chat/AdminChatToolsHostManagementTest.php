<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Entity\Series;
use App\Entity\User;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

#[Group('functional')]
final class AdminChatToolsHostManagementTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private ChatToolRegistry $registry;

    private ChatActor $actor;

    private Series $series;

    private User $candidate;

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

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->candidate = UserAssembler::new()->withEmail('host@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $this->series = SeriesAssembler::new()->assemble();
        $this->em->persist($admin);
        $this->em->persist($this->candidate);
        $this->em->persist($this->series);
        $this->em->flush();

        $this->actor = new ChatActor($admin, ['ROLE_ADMIN']);
    }

    public function testGrantHostRoleKeepsExistingRolesAndIsIdempotent(): void
    {
        $arguments = [
            'confirm' => true,
            'email' => 'HOST@example.com',
        ];

        $first = $this->registry->call('admin.grant_host_role', $this->actor, $arguments);
        $second = $this->registry->call('admin.grant_host_role', $this->actor, $arguments);

        static::assertTrue($first->ok);
        static::assertFalse($first->data['already_host']);
        static::assertTrue($second->ok);
        static::assertTrue($second->data['already_host']);

        $this->em->clear();
        $reloaded = $this->em->find(User::class, $this->candidate->getId());
        static::assertInstanceOf(User::class, $reloaded);
        static::assertSame(['ROLE_ADMIN', 'ROLE_HOST'], $reloaded->getRoles());
    }

    public function testGrantHostRoleRequiresConfirmation(): void
    {
        $result = $this->registry->call('admin.grant_host_role', $this->actor, [
            'user_id' => $this->candidate->getId(),
        ]);

        static::assertFalse($result->ok);
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $this->candidate->getId());
        static::assertInstanceOf(User::class, $reloaded);
        static::assertFalse($reloaded->hasRole('ROLE_HOST'));
    }

    public function testGrantHostRoleFailsForUnknownUser(): void
    {
        $result = $this->registry->call('admin.grant_host_role', $this->actor, [
            'confirm' => true,
            'email' => 'nobody@example.com',
        ]);

        static::assertFalse($result->ok);
    }

    public function testAssignSeriesInstructorIsIdempotent(): void
    {
        $arguments = [
            'confirm' => true,
            'series_id' => (string) $this->series->getId(),
            'user_id' => $this->candidate->getId(),
        ];

        $first = $this->registry->call('admin.assign_series_instructor', $this->actor, $arguments);
        $second = $this->registry->call('admin.assign_series_instructor', $this->actor, $arguments);

        static::assertTrue($first->ok);
        static::assertFalse($first->data['already_instructor']);
        static::assertFalse($first->data['has_host_role']);
        static::assertTrue($second->ok);
        static::assertTrue($second->data['already_instructor']);

        $this->em->clear();
        $reloaded = $this->em->find(Series::class, $this->series->getId());
        static::assertInstanceOf(Series::class, $reloaded);
        $instructors = $reloaded->getInstructors();
        static::assertCount(1, $instructors);
        $instructor = $instructors->first();
        static::assertInstanceOf(User::class, $instructor);
        static::assertSame($this->candidate->getId(), $instructor->getId());
    }

    public function testAssignSeriesInstructorFailsForUnknownSeriesOrUser(): void
    {
        $unknownSeries = $this->registry->call('admin.assign_series_instructor', $this->actor, [
            'confirm' => true,
            'series_id' => (string) new Ulid(),
            'user_id' => $this->candidate->getId(),
        ]);
        $invalidSeries = $this->registry->call('admin.assign_series_instructor', $this->actor, [
            'confirm' => true,
            'series_id' => 'not-a-ulid',
            'user_id' => $this->candidate->getId(),
        ]);
        $unknownUser = $this->registry->call('admin.assign_series_instructor', $this->actor, [
            'confirm' => true,
            'series_id' => (string) $this->series->getId(),
            'email' => 'nobody@example.com',
        ]);

        static::assertFalse($unknownSeries->ok);
        static::assertFalse($invalidSeries->ok);
        static::assertFalse($unknownUser->ok);
    }

    public function testSetOperationalEmailsOptsUserOutAndIn(): void
    {
        $optOut = $this->registry->call('admin.set_operational_emails', $this->actor, [
            'confirm' => true,
            'email' => 'host@example.com',
            'enabled' => false,
        ]);

        static::assertTrue($optOut->ok);
        static::assertFalse($optOut->data['operational_emails']);
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $this->candidate->getId());
        static::assertInstanceOf(User::class, $reloaded);
        static::assertFalse($reloaded->receivesOperationalEmails());

        $optIn = $this->registry->call('admin.set_operational_emails', $this->actor, [
            'confirm' => true,
            'user_id' => $this->candidate->getId(),
            'enabled' => true,
        ]);

        static::assertTrue($optIn->ok);
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $this->candidate->getId());
        static::assertInstanceOf(User::class, $reloaded);
        static::assertTrue($reloaded->receivesOperationalEmails());
    }

    public function testSetOperationalEmailsRequiresConfirmationAndKnownUser(): void
    {
        $unconfirmed = $this->registry->call('admin.set_operational_emails', $this->actor, [
            'user_id' => $this->candidate->getId(),
            'enabled' => false,
        ]);
        $unknown = $this->registry->call('admin.set_operational_emails', $this->actor, [
            'confirm' => true,
            'email' => 'nobody@example.com',
            'enabled' => false,
        ]);

        static::assertFalse($unconfirmed->ok);
        static::assertFalse($unknown->ok);
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $this->candidate->getId());
        static::assertInstanceOf(User::class, $reloaded);
        static::assertTrue($reloaded->receivesOperationalEmails());
    }

    public function testToolsAreAdminOnly(): void
    {
        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $this->em->persist($host);
        $this->em->flush();

        $result = $this->registry->call('admin.grant_host_role', new ChatActor($host, ['ROLE_HOST']), [
            'confirm' => true,
            'user_id' => $this->candidate->getId(),
        ]);

        static::assertFalse($result->ok);
    }
}

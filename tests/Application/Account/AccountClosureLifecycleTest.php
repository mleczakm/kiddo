<?php

declare(strict_types=1);

namespace App\Tests\Application\Account;

use App\Application\Command\AnonymizeExpiredAccounts;
use App\Application\Command\AnonymizeUser;
use App\Application\Command\RequestAccountClosure;
use App\Entity\Child;
use App\Entity\ConsentType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
final class AccountClosureLifecycleTest extends KernelTestCase
{
    use InteractsWithMailer;

    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testPermanentClosureStartsGracePeriodRecordsConsentAndEmailsTheUser(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        [$em, $bus] = [$this->em(), $this->bus()];

        $user = UserAssembler::new()->withEmail('closing@example.test')->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();

        $bus->dispatch(new RequestAccountClosure((int) $user->getId(), true));
        $em->refresh($user);

        static::assertNotNull($user->getLifecycle()->deletionRequestedAt());
        static::assertNotNull($user->getLifecycle()->deactivatedAt());
        $this->mailer()->assertEmailSentTo('closing@example.test', 'Twoje konto zostanie usunięte');

        /** @var UserConsentRepository $consents */
        $consents = $container->get(UserConsentRepository::class);
        $latest = $consents->findLatestActive($user, ConsentType::ACCOUNT_DELETION);
        static::assertNotNull($latest);
    }

    public function testSweepOnlyAnonymisesAccountsPastTheGracePeriod(): void
    {
        Clock::set(new MockClock('2026-10-01 04:00:00'));
        self::bootKernel();
        $em = $this->em();

        $due = $this->userWithDeletionRequestedAt($em, new \DateTimeImmutable('2026-09-10 10:00:00'));
        $child = new Child($due, 'Zosia', new \DateTimeImmutable('2022-01-01'));
        $em->persist($child);
        $fresh = $this->userWithDeletionRequestedAt($em, new \DateTimeImmutable('2026-09-28 10:00:00'));
        $em->flush();

        $this->bus()->dispatch(new AnonymizeExpiredAccounts());
        $em->clear();

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $anonymised = $users->find($due->getId());
        static::assertNotNull($anonymised);
        static::assertTrue($anonymised->getLifecycle()->isAnonymized());
        static::assertSame('Użytkownik usunięty', $anonymised->getName());
        static::assertStringContainsString('@kiddo.invalid', $anonymised->getEmail());

        $stillPending = $users->find($fresh->getId());
        static::assertNotNull($stillPending);
        static::assertFalse($stillPending->getLifecycle()->isAnonymized());
    }

    public function testAnonymiseIsIdempotent(): void
    {
        self::bootKernel();
        $em = $this->em();
        $user = $this->userWithDeletionRequestedAt($em, new \DateTimeImmutable('-30 days'));
        $em->flush();

        $this->bus()->dispatch(new AnonymizeUser((int) $user->getId()));
        $em->refresh($user);
        $firstStamp = $user->getLifecycle()->anonymizedAt();
        static::assertNotNull($firstStamp);

        $this->bus()->dispatch(new AnonymizeUser((int) $user->getId()));
        $em->refresh($user);
        static::assertEquals($firstStamp, $user->getLifecycle()->anonymizedAt());
    }

    private function userWithDeletionRequestedAt(EntityManagerInterface $em, \DateTimeImmutable $requestedAt): User
    {
        $user = UserAssembler::new()
            ->withEmail(sprintf('pending-%s@example.test', bin2hex(random_bytes(4))))
            ->withRoles('ROLE_USER')
            ->assemble();
        $user->getLifecycle()->requestDeletion($requestedAt);
        $em->persist($user);

        return $user;
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function bus(): MessageBusInterface
    {
        /** @var MessageBusInterface */
        return self::getContainer()->get(MessageBusInterface::class);
    }
}

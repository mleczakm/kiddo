<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\BookingNotificationRecipients;
use App\Entity\FinanceContact;
use App\Entity\User;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class BookingNotificationRecipientsTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private BookingNotificationRecipients $recipients;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        $recipients = self::getContainer()->get(BookingNotificationRecipients::class);
        static::assertInstanceOf(BookingNotificationRecipients::class, $recipients);
        $this->recipients = $recipients;
    }

    public function testOperationalAudienceIsEveryAdminPlusFinanceContactsWithoutDuplicates(): void
    {
        $admin = $this->persistUser('admin@example.com', 'ROLE_ADMIN');
        $adminAndFinance = $this->persistUser('both@example.com', 'ROLE_ADMIN');
        $accountant = $this->persistUser('accountant@example.com', 'ROLE_USER');
        $host = $this->persistUser('host@example.com', 'ROLE_HOST');
        $customer = $this->persistUser('customer@example.com', 'ROLE_USER');
        $this->em->persist(new FinanceContact($adminAndFinance));
        $this->em->persist(new FinanceContact($accountant));
        $this->em->flush();

        $emails = $this->emails($this->recipients->operational());

        static::assertEqualsCanonicalizing(
            [$admin->getEmail(), $adminAndFinance->getEmail(), $accountant->getEmail()],
            $emails,
        );
        static::assertNotContains($host->getEmail(), $emails);
        static::assertNotContains($customer->getEmail(), $emails);
    }

    public function testOptedOutUsersAreLeftOutEvenWhenFinanceContacts(): void
    {
        $admin = $this->persistUser('admin@example.com', 'ROLE_ADMIN');
        $superAdmin = $this->persistUser('super@example.com', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN');
        $superAdmin->setOperationalEmails(false);
        $optedOutContact = $this->persistUser('contact@example.com', 'ROLE_USER');
        $optedOutContact->setOperationalEmails(false);
        $this->em->persist(new FinanceContact($optedOutContact));
        $this->em->flush();

        static::assertSame([$admin->getEmail()], $this->emails($this->recipients->operational()));
    }

    public function testExcludeDropsTheActor(): void
    {
        $actor = $this->persistUser('actor@example.com', 'ROLE_ADMIN');
        $other = $this->persistUser('other@example.com', 'ROLE_ADMIN');

        static::assertSame([$other->getEmail()], $this->emails($this->recipients->operational(exclude: $actor)));
    }

    public function testLessonAudienceKeepsInstructorsEvenWhenAdminOptedOut(): void
    {
        $admin = $this->persistUser('admin@example.com', 'ROLE_ADMIN');
        $optedOutAdminHost = $this->persistUser('hostadmin@example.com', 'ROLE_ADMIN', 'ROLE_HOST');
        $optedOutAdminHost->setOperationalEmails(false);
        $lesson = LessonAssembler::new()->assemble();
        $lesson->addInstructor($optedOutAdminHost);
        $this->em->persist($lesson);
        $this->em->flush();

        $emails = $this->emails($this->recipients->forLessons([$lesson]));

        static::assertEqualsCanonicalizing([$admin->getEmail(), $optedOutAdminHost->getEmail()], $emails);
    }

    private function persistUser(string $email, string ...$roles): User
    {
        $user = UserAssembler::new()->withEmail($email)->withRoles(...$roles)->assemble();
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @param list<User> $users
     * @return list<string>
     */
    private function emails(array $users): array
    {
        return array_map(static fn(User $user): string => $user->getEmail(), $users);
    }
}

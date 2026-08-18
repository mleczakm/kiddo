<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AddUserModal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AddUserModalTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testMountDeniesAccessWithoutManageUsersRole(): void
    {
        $customer = UserAssembler::new()->withRoles('ROLE_USER')->assemble();
        $this->em->persist($customer);
        $this->em->flush();

        $this->client->loginUser($customer);

        $this->expectException(AccessDeniedException::class);

        $this->createLiveComponent(AddUserModal::class, client: $this->client)->render();
    }

    public function testAdminCanCreateAUserWithAllFields(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(AddUserModal::class, client: $this->client);
        $component->set('name', 'Testowy Użytkownik');
        $component->set('email', 'Testowy.Uzytkownik@Example.com');
        $component->set('phone', '600 123 456');
        $component->set('adminNote', 'Utworzony przez test automatyczny.');
        $component->set('newsletterSubscribed', true);
        $component->call('toggleRole', [
            'role' => 'ROLE_HOST',
        ]);
        $component->call('save');

        $this->assertComponentEmitEvent($component, 'userModalSaved');

        /** @var AddUserModal $state */
        $state = $component->component();
        self::assertSame([], $state->errors);

        $user = $this->em
            ->getRepository(User::class)
            ->findOneBy([
                'email' => 'testowy.uzytkownik@example.com',
            ]);
        self::assertNotNull($user, 'user must be persisted with the lower-cased email');
        self::assertSame('Testowy Użytkownik', $user->getName());
        self::assertSame(['ROLE_HOST', 'ROLE_USER'], $user->getRoles());
        self::assertNotNull($user->getPhone());
        self::assertSame('Utworzony przez test automatyczny.', $user->getAdminNote());
        self::assertTrue($user->isNewsletterSubscribed());
        self::assertNotNull($user->getNewsletterConsentDate());

        $logs = $this->em->getRepository(ActivityLog::class)->findBySubject($user);
        self::assertCount(1, $logs);
    }

    public function testRoleUserIsAlwaysIncludedEvenWhenNoRoleIsChecked(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(AddUserModal::class, client: $this->client);
        $component->set('name', 'Zwykły Klient');
        $component->set('email', 'zwykly.klient@example.com');
        $component->call('save');

        $user = $this->em
            ->getRepository(User::class)
            ->findOneBy([
                'email' => 'zwykly.klient@example.com',
            ]);
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testBlankNameAndEmailAreRejectedAndNothingIsPersisted(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        $countBefore = $this->em->getRepository(User::class)->count([]);

        $component = $this->createLiveComponent(AddUserModal::class, client: $this->client);
        $component->set('name', '   ');
        $component->set('email', '   ');
        $component->call('save');

        /** @var AddUserModal $state */
        $state = $component->component();
        self::assertArrayHasKey('name', $state->errors);
        self::assertArrayHasKey('email', $state->errors);
        self::assertSame($countBefore, $this->em->getRepository(User::class)->count([]));
    }

    public function testInvalidEmailFormatIsRejected(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(AddUserModal::class, client: $this->client);
        $component->set('name', 'Ktoś');
        $component->set('email', 'nie-jest-mailem');
        $component->call('save');

        /** @var AddUserModal $state */
        $state = $component->component();
        self::assertArrayHasKey('email', $state->errors);
    }

    public function testDuplicateEmailIsRejectedAndNoSecondUserIsCreated(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $existing = UserAssembler::new()->withEmail('istnieje@example.com')->assemble();
        $this->em->persist($existing);
        $this->em->flush();

        $this->client->loginUser($admin);

        $countBefore = $this->em->getRepository(User::class)->count([]);

        $component = $this->createLiveComponent(AddUserModal::class, client: $this->client);
        $component->set('name', 'Kopia');
        $component->set('email', 'ISTNIEJE@example.com');
        $component->call('save');

        /** @var AddUserModal $state */
        $state = $component->component();
        self::assertArrayHasKey('email', $state->errors);
        self::assertSame($countBefore, $this->em->getRepository(User::class)->count([]));
    }

    public function testInvalidPhoneNumberIsRejected(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(AddUserModal::class, client: $this->client);
        $component->set('name', 'Ktoś');
        $component->set('email', 'ktos@example.com');
        $component->set('phone', 'nie-numer-telefonu');
        $component->call('save');

        /** @var AddUserModal $state */
        $state = $component->component();
        self::assertArrayHasKey('phone', $state->errors);

        $user = $this->em
            ->getRepository(User::class)
            ->findOneBy([
                'email' => 'ktos@example.com',
            ]);
        self::assertNull($user, 'must not persist while any field is invalid');
    }

    public function testToggleRoleAddsThenRemovesTheRole(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(AddUserModal::class, client: $this->client);
        $component->call('toggleRole', [
            'role' => 'ROLE_MANAGE_USERS',
        ]);

        /** @var AddUserModal $state */
        $state = $component->component();
        self::assertSame(['ROLE_MANAGE_USERS'], $state->roles);

        $component->call('toggleRole', [
            'role' => 'ROLE_MANAGE_USERS',
        ]);

        /** @var AddUserModal $state */
        $state = $component->component();
        self::assertSame([], $state->roles);
    }

    public function testToggleRoleIgnoresUnknownRoles(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(AddUserModal::class, client: $this->client);
        $component->call('toggleRole', [
            'role' => 'ROLE_NOT_A_REAL_ROLE',
        ]);

        /** @var AddUserModal $state */
        $state = $component->component();
        self::assertSame([], $state->roles);
    }
}

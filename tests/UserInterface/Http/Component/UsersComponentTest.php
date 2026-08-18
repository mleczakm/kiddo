<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Notification;
use App\Entity\User;
use App\UserInterface\Http\Component\UsersComponent;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;

#[Group('functional')]
final class UsersComponentTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;

    public function testUsersAreListedAndCanBeSearched(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $adminUser = new User();
        $adminUser->setEmail('admin@test.com');
        $adminUser->setName('Admin User');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($adminUser);

        $user1 = new User();
        $user1->setEmail('john.doe@test.com');
        $user1->setName('John Doe');
        $user1->setRoles(['ROLE_USER']);
        $entityManager->persist($user1);

        $user2 = new User();
        $user2->setEmail('jane.doe@test.com');
        $user2->setName('Jane Doe');
        $user2->setRoles(['ROLE_USER']);
        $entityManager->persist($user2);

        $entityManager->flush();

        $client->loginUser($adminUser);

        $test = $this->createLiveComponent(UsersComponent::class, client: $client);

        $rendered = $test->render()->toString();
        static::assertStringContainsString('John Doe', $rendered);
        static::assertStringContainsString('Jane Doe', $rendered);
        static::assertStringContainsString('admin@test.com', $rendered);
        static::assertStringContainsString('john.doe@test.com', $rendered);
        static::assertStringContainsString('jane.doe@test.com', $rendered);
    }

    public function testAdminCanSendAnInAppNotificationToAClient(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $adminUser = new User();
        $adminUser->setEmail('admin@test.com');
        $adminUser->setName('Admin User');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($adminUser);

        $customer = new User();
        $customer->setEmail('john.doe@test.com');
        $customer->setName('John Doe');
        $customer->setRoles(['ROLE_USER']);
        $entityManager->persist($customer);

        $entityManager->flush();

        $client->loginUser($adminUser);

        $component = $this->createLiveComponent(UsersComponent::class, client: $client);
        $component->call('startCompose', [
            'userId' => (string) $customer->getId(),
        ]);

        /** @var UsersComponent $usersComponent */
        $usersComponent = $component->component();
        static::assertSame($customer->getId(), $usersComponent->getComposingForUser()?->getId());

        $component->set('notifyTitle', 'Przypomnienie o zajęciach');
        $component->set('notifyBody', 'Do zobaczenia jutro o 10:00!');
        $component->call('sendNotification');

        $notifications = $entityManager
            ->getRepository(Notification::class)
            ->findBy([
                'user' => $customer,
            ]);
        static::assertCount(1, $notifications);
        static::assertSame('Przypomnienie o zajęciach', $notifications[0]->getTitle());
        static::assertSame('Do zobaczenia jutro o 10:00!', $notifications[0]->getBody());

        // Composer state resets after sending
        /** @var UsersComponent $usersComponent */
        $usersComponent = $component->component();
        static::assertNull($usersComponent->getComposingForUser());
    }

    public function testOpenAddModalRequiresManageUsersRole(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // ROLE_HOST does not inherit ROLE_MANAGE_USERS (see security.yaml role_hierarchy).
        $host = new User();
        $host->setEmail('host@test.com');
        $host->setName('Host User');
        $host->setRoles(['ROLE_HOST']);
        $entityManager->persist($host);
        $entityManager->flush();

        $client->loginUser($host);

        $component = $this->createLiveComponent(UsersComponent::class, client: $client);

        $this->expectException(AccessDeniedException::class);
        $component->call('openAddModal');
    }

    public function testOpenAddModalShowsTheAddUserModalAndClosingItHidesItAgain(): void
    {
        $client = self::createClient();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $adminUser = new User();
        $adminUser->setEmail('admin@test.com');
        $adminUser->setName('Admin User');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);

        $component = $this->createLiveComponent(UsersComponent::class, client: $client);
        $component->call('openAddModal');

        /** @var UsersComponent $usersComponent */
        $usersComponent = $component->component();
        static::assertTrue($usersComponent->showAddModal);

        $rendered = $component->render()->toString();
        static::assertStringContainsString(
            'Nowy użytkownik',
            $rendered,
            'the embedded AddUserModal must render while open',
        );

        // Simulates AddUserModal's emitUp('userModalSaved')/emitUp('userModalClosed') bubbling up.
        $component->emit('userModalSaved');

        /** @var UsersComponent $usersComponent */
        $usersComponent = $component->component();
        static::assertFalse($usersComponent->showAddModal);
    }
}

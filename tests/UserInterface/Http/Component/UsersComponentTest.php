<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\Notification;
use App\Entity\User;
use App\UserInterface\Http\Component\UsersComponent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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

        $rendered = $test->render()
            ->toString();
        $this->assertStringContainsString('John Doe', $rendered);
        $this->assertStringContainsString('Jane Doe', $rendered);
        $this->assertStringContainsString('admin@test.com', $rendered);
        $this->assertStringContainsString('john.doe@test.com', $rendered);
        $this->assertStringContainsString('jane.doe@test.com', $rendered);
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
        $component->call('startCompose', ['userId' => (string) $customer->getId()]);

        self::assertSame($customer->getId(), $component->component()->getComposingForUser()?->getId());

        $component->set('notifyTitle', 'Przypomnienie o zajęciach');
        $component->set('notifyBody', 'Do zobaczenia jutro o 10:00!');
        $component->call('sendNotification');

        $notifications = $entityManager->getRepository(Notification::class)->findBy(['user' => $customer]);
        self::assertCount(1, $notifications);
        self::assertSame('Przypomnienie o zajęciach', $notifications[0]->getTitle());
        self::assertSame('Do zobaczenia jutro o 10:00!', $notifications[0]->getBody());

        // Composer state resets after sending
        self::assertNull($component->component()->getComposingForUser());
    }
}

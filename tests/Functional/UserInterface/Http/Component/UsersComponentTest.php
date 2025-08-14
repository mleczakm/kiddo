<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Component;

use App\Entity\User;
use App\UserInterface\Http\Component\UsersComponent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;

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
}

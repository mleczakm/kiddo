<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

#[Group('functional')]
final class AdminDashboardComponentTest extends WebTestCase
{
    use Factories;

    public function testAdminDashboardIsAccessibleToAdmin(): void
    {
        $client = self::createClient();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $adminUser = new User();
        $adminUser->setEmail('admin@test.com');
        $adminUser->setName('Admin User');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);
        $crawler = $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Panel Administratora');
    }

    public function testAdminDashboardRedirectsUnauthenticatedUser(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects('http://localhost/login');
    }
}
